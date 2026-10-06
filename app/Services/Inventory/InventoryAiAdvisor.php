<?php

namespace App\Services\Inventory;

use App\Models\Business;
use App\Models\InventoryAiAdviceLog;
use App\Models\InventoryDailyConsumption;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Services\AiGateway\CapabilityInvokeClient;
use App\Support\SharedTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Builds inventory facts and asks the AI gateway for advice only.
 * Never creates orders, transfers, or write-offs.
 */
class InventoryAiAdvisor
{
    public const HISTORY_WEEKS = 12;

    public const USE_CASES = [
        'stockout' => [
            'capability' => 'STOCKOUT_RISK',
            'label' => 'Check stockout risk',
            'short' => 'What might run out',
            'help' => 'Items in this scope that may run short soon.',
            'needs_history' => true,
        ],
        'demand' => [
            'capability' => 'DEMAND_FORECAST',
            'label' => 'Ask for a demand forecast',
            'short' => 'How much we may need',
            'help' => 'A draft of what this scope may need over the next 4 weeks.',
            'needs_history' => true,
        ],
        'consumption' => [
            'capability' => 'CONSUMPTION_FORECAST',
            'label' => 'Ask for a consumption forecast',
            'short' => 'How much we may use',
            'help' => 'A draft of usage for the next 4 weeks, from your history.',
            'needs_history' => true,
        ],
        'wastage' => [
            'capability' => 'WASTAGE_PATTERN',
            'label' => 'Look for wastage patterns',
            'short' => 'What we are wasting',
            'help' => 'Patterns in expired or written-off stock.',
            'needs_history' => true,
        ],
        'ask' => [
            'capability' => 'AGENT_RUN',
            'label' => 'Ask before ordering',
            'short' => 'Ask a question',
            'help' => 'Write your own question before anyone orders.',
            'needs_history' => false,
        ],
    ];

    public function __construct(private readonly CapabilityInvokeClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured('inventory');
    }

    public function gatewayUrl(): string
    {
        return $this->client->url();
    }

    /**
     * @return array{
     *     ok: bool,
     *     available: bool,
     *     capability: string,
     *     title: string,
     *     summary: ?string,
     *     lines: list<string>,
     *     warnings: list<string>,
     *     requiresHumanReview: bool,
     *     requestId: ?string,
     *     error: ?string,
     *     errorCode: ?string,
     *     details: mixed
     * }
     */
    public function advise(
        string $useCase,
        int $businessId,
        ?int $storeId = null,
        ?int $itemId = null,
        ?string $question = null,
        array $scopeItemIds = [],
    ): array {
        $meta = self::USE_CASES[$useCase] ?? null;
        if ($meta === null) {
            return $this->localFailure('Unknown AI task.', $useCase);
        }

        $scopeItemIds = $this->scopeIds($itemId, $scopeItemIds);
        $itemId = count($scopeItemIds) === 1 ? $scopeItemIds[0] : $itemId;

        $capability = $meta['capability'];
        $historySource = $useCase === 'wastage' ? 'wastage' : 'demand';
        $timezone = $this->timezoneFor($businessId, $storeId);
        $history = $this->weeklyHistory($businessId, $storeId, null, $historySource, $timezone, $scopeItemIds);
        $snapshot = $this->stockSnapshot($businessId, $storeId, null, $scopeItemIds === [] ? 8 : max(8, count($scopeItemIds)), $scopeItemIds);

        if ($meta['needs_history'] && count($this->usableHistory($history)) < 3) {
            $scope = $this->focusPhrase($businessId, $scopeItemIds);

            return $this->localFailure(
                'Need at least three weeks of history'
                .($scope !== null ? ' for '.$scope : '')
                .' before AI can review this order. Inventory numbers stay as they are.',
                $useCase,
            );
        }

        $measure = $this->measure($useCase, $businessId, $storeId, $scopeItemIds, $snapshot, $question);
        $input = $useCase === 'ask'
            ? [
                'goal' => $this->askGoal($measure, $question),
                'profileCode' => 'DEFAULT',
            ]
            : [
                'measure' => $measure,
                'grain' => 'WEEK',
                'horizon' => 'P4W',
                'timezone' => $timezone,
                'history' => $history,
            ];

        $response = $this->awaitIfQueued(
            $this->client->invoke(
                $capability,
                $input,
                'inventory',
                null,
                $this->tenantId($businessId),
            ),
            $businessId,
        );

        $result = is_array($response['result'] ?? null) ? $response['result'] : null;
        $warnings = $response['warnings'];
        foreach ($this->stringList($result['warnings'] ?? []) as $warning) {
            $warnings[] = $warning;
        }

        $briefing = $this->briefing($businessId, $storeId, $itemId, $useCase, $history, $snapshot, $timezone);
        if (count($scopeItemIds) > 1 || ($briefing['item_name'] === null && $scopeItemIds !== [])) {
            $briefing['scope_label'] = $this->focusPhrase($businessId, $scopeItemIds)
                .($briefing['store_name'] ? ' at '.$briefing['store_name'] : '');
            $briefing['item_name'] = null;
        }
        $sent = [
            'capability' => $capability,
            'purposeOfUse' => 'REPLENISHMENT_PLANNING',
            'dataClassification' => 'CONFIDENTIAL',
            'input' => $input,
        ];
        $received = [
            'ok' => $response['ok'],
            'status' => $response['status'] ?? null,
            'requestId' => $response['requestId'],
            'result' => $result,
            'warnings' => array_values(array_unique($warnings)),
            'error' => $response['error'],
            'errorCode' => $response['errorCode'],
            'details' => $response['details'],
        ];

        $this->recordLog(
            $useCase,
            $capability,
            $meta['label'],
            $businessId,
            $storeId,
            $itemId,
            $question,
            $sent,
            $received,
            $response,
            $this->summaryFrom($result, $useCase),
        );

        return [
            'ok' => $response['ok'],
            'available' => $response['available'],
            'capability' => $capability,
            'title' => $meta['label'],
            'summary' => $this->summaryFrom($result, $useCase),
            'lines' => $this->linesFrom($result),
            'series' => $this->seriesFrom($result),
            'assumptions' => $this->listFrom($result, 'assumptions'),
            'risks' => $this->listFrom($result, 'risks'),
            'patterns' => $this->listFrom($result, 'patterns'),
            'warnings' => array_values(array_unique($warnings)),
            'requiresHumanReview' => true,
            'requestId' => $response['requestId'],
            'error' => $response['error'],
            'errorCode' => $response['errorCode'],
            'details' => $response['details'],
            'briefing' => $briefing,
            'sent' => $sent,
            'received' => $received,
        ];
    }

    /**
     * Facts the operator can read before or after asking the gateway.
     *
     * @param  list<array{period: string, value: float|null, missing: bool}>|null  $history
     * @param  list<array{name: string, code: ?string, on_hand: float, ma_15: float}>|null  $snapshot
     * @return array{
     *     store_name: ?string,
     *     item_name: ?string,
     *     scope_label: string,
     *     unit_label: string,
     *     timezone: string,
     *     horizon_label: string,
     *     history: list<array{period: string, value: float|null, missing: bool}>,
     *     usable_weeks: int,
     *     history_total: float,
     *     average_week: float,
     *     snapshot: list<array{name: string, code: ?string, on_hand: float, ma_15: float}>
     * }
     */
    public function briefing(
        int $businessId,
        ?int $storeId = null,
        ?int $itemId = null,
        string $useCase = 'consumption',
        ?array $history = null,
        ?array $snapshot = null,
        ?string $timezone = null,
    ): array {
        $timezone ??= $this->timezoneFor($businessId, $storeId);
        $source = $useCase === 'wastage' ? 'wastage' : 'demand';
        $history ??= $this->weeklyHistory($businessId, $storeId, $itemId, $source, $timezone);
        $snapshot ??= $this->stockSnapshot($businessId, $storeId, $itemId);
        $usable = $this->usableHistory($history);
        $total = array_sum(array_map(fn (array $point): float => (float) $point['value'], $usable));
        $storeName = $storeId ? Store::query()->where('business_id', $businessId)->find($storeId)?->name : null;
        $itemName = $this->itemLabel($businessId, $itemId);

        if ($itemName !== null) {
            $scope = $itemName.($storeName ? ' at '.$storeName : '');
        } elseif ($storeName) {
            $scope = 'All items at '.$storeName;
        } else {
            $scope = 'All stores in this organisation';
        }

        return [
            'store_name' => $storeName,
            'item_name' => $itemName,
            'scope_label' => $scope,
            'unit_label' => $itemName
                ? 'Sale units (SUOM) for this item only'
                : 'Sale units (SUOM) added across every item in scope. Litres, tablets, and vials are summed together — this is not money.',
            'timezone' => $timezone,
            'horizon_label' => 'Next 4 weeks',
            'history' => $history,
            'usable_weeks' => count($usable),
            'history_total' => $total,
            'average_week' => count($usable) > 0 ? $total / count($usable) : 0.0,
            'snapshot' => $snapshot,
        ];
    }

    public static function formatIsoWeek(string $period): string
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $period, $matches)) {
            return $period;
        }

        $start = Carbon::now()->setISODate((int) $matches[1], (int) $matches[2])->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        if ($start->isSameMonth($end)) {
            return $start->format('j').'–'.$end->format('j M');
        }

        return $start->format('j M').'–'.$end->format('j M');
    }

    /**
     * @param  list<array{period: string, value: float|null, missing: bool}>  $history
     * @return list<array{period: string, value: float|null, missing: bool}>
     */
    public function usableHistory(array $history): array
    {
        return array_values(array_filter(
            $history,
            fn (array $point): bool => ($point['missing'] ?? false) !== true && $point['value'] !== null
        ));
    }

    /**
     * @return list<array{period: string, value: float|null, missing: bool}>
     */
    public function weeklyHistory(
        int $businessId,
        ?int $storeId,
        ?int $itemId,
        string $source = 'demand',
        ?string $timezone = null,
        array $itemIds = [],
    ): array {
        $timezone ??= $this->timezoneFor($businessId, $storeId);
        $end = Carbon::now($timezone)->startOfWeek(Carbon::MONDAY);
        $from = $end->copy()->subWeeks(self::HISTORY_WEEKS - 1)->toDateString();
        $scopeItemIds = $this->scopeIds($itemId, $itemIds);

        $query = InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->whereDate('consumption_date', '>=', $from)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($scopeItemIds !== [], fn ($q) => $q->whereIn('item_id', $scopeItemIds));

        if ($source === 'wastage') {
            $query->where('source', InventoryDailyConsumption::SOURCE_WASTAGE_EXPIRED);
        } else {
            $query->where('source', '!=', InventoryDailyConsumption::SOURCE_WASTAGE_EXPIRED);
        }

        $rows = $query
            ->selectRaw('consumption_date, SUM(quantity_suom) as value')
            ->groupBy('consumption_date')
            ->get();

        $byPeriod = [];
        foreach ($rows as $row) {
            $period = $this->isoWeekPeriod(Carbon::parse($row->consumption_date)->timezone($timezone));
            $byPeriod[$period] = ($byPeriod[$period] ?? 0) + (float) $row->value;
        }

        return $this->fillWeeklyWindow($byPeriod, $end, self::HISTORY_WEEKS);
    }

    /**
     * @param  array<string, float>  $byPeriod
     * @return list<array{period: string, value: float|null, missing: bool}>
     */
    public function fillWeeklyWindow(array $byPeriod, Carbon $endMonday, int $weeks = self::HISTORY_WEEKS): array
    {
        $history = [];
        $start = $endMonday->copy()->subWeeks($weeks - 1)->startOfWeek(Carbon::MONDAY);

        for ($i = 0; $i < $weeks; $i++) {
            $period = $this->isoWeekPeriod($start->copy()->addWeeks($i));
            $hasValue = array_key_exists($period, $byPeriod);
            $history[] = [
                'period' => $period,
                'value' => $hasValue ? (float) $byPeriod[$period] : null,
                'missing' => ! $hasValue,
            ];
        }

        return $history;
    }

    /**
     * @return list<array{name: string, code: ?string, on_hand: float, ma_15: float}>
     */
    public function stockSnapshot(int $businessId, ?int $storeId, ?int $itemId = null, int $limit = 8, array $itemIds = []): array
    {
        $scopeItemIds = $this->scopeIds($itemId, $itemIds);
        $limit = min(40, max(1, $scopeItemIds === [] ? $limit : max($limit, count($scopeItemIds))));

        return InventoryStockLevel::query()
            ->where('inventory_stock_levels.business_id', $businessId)
            ->when($storeId, fn ($q) => $q->where('inventory_stock_levels.store_id', $storeId))
            ->when($scopeItemIds !== [], fn ($q) => $q->whereIn('inventory_stock_levels.item_id', $scopeItemIds))
            ->where(function ($q) {
                $q->whereNull('inventory_stock_levels.stock_zone')
                    ->orWhere('inventory_stock_levels.stock_zone', 'active');
            })
            ->join('items', 'items.id', '=', 'inventory_stock_levels.item_id')
            ->orderBy('inventory_stock_levels.quantity_suom')
            ->limit($limit)
            ->get([
                'items.name as item_name',
                'items.code as item_code',
                'inventory_stock_levels.quantity_suom',
                'inventory_stock_levels.ma_15_days',
            ])
            ->map(fn ($row): array => [
                'name' => (string) $row->item_name,
                'code' => $row->item_code ? (string) $row->item_code : null,
                'on_hand' => (float) $row->quantity_suom,
                'ma_15' => (float) ($row->ma_15_days ?? 0),
            ])
            ->all();
    }

    /**
     * @param  list<array{name: string, code: ?string, on_hand: float, ma_15: float}>  $snapshot
     */
    private function measure(
        string $useCase,
        int $businessId,
        ?int $storeId,
        array $scopeItemIds,
        array $snapshot,
        ?string $question,
    ): string {
        $storeName = $storeId ? Store::query()->where('business_id', $businessId)->find($storeId)?->name : null;
        $scope = $storeName ? 'store '.$storeName : 'this organisation';
        $focus = $this->focusPhrase($businessId, $scopeItemIds);

        $itemBits = Collection::make($snapshot)
            ->take(6)
            ->map(function (array $item): string {
                $label = $item['code'] ? $item['name'].' ('.$item['code'].')' : $item['name'];

                return $label.': on-hand '.number_format($item['on_hand'], 1).', 15-day MA '.number_format($item['ma_15'], 2);
            })
            ->implode('; ');

        $base = match ($useCase) {
            'stockout' => 'stockout_risk. Stockout risk for '.$scope.' over the next four weeks. Inventory rules remain authoritative.',
            'demand' => 'weekly_demand. Weekly demand for '.$scope.' over the next four weeks. Do not create replenishment orders.',
            'consumption' => 'weekly_consumption. Weekly consumption for '.$scope.' over the next four weeks. Never modify source history.',
            'wastage' => 'wastage_pattern. Wastage and expiry patterns for '.$scope.'. Do not write off stock.',
            default => 'What should we check before ordering for '.$scope.'?',
        };

        if ($focus !== null) {
            $base .= ' Focus on '.$focus.'.';
        }

        if ($itemBits !== '') {
            $base .= ' Current picture: '.$itemBits.'.';
        }

        if (is_string($question) && trim($question) !== '') {
            $base .= ' Operator note: '.trim($question);
        }

        return $base;
    }

    private function askGoal(string $measure, ?string $question): string
    {
        $asked = is_string($question) && trim($question) !== ''
            ? trim($question)
            : 'What should we check before we order more?';

        return $asked.' '.$measure;
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function summaryFrom(?array $result, string $useCase = 'stockout'): ?string
    {
        if ($result === null) {
            return null;
        }

        foreach (['summary', 'narrative', 'answer'] as $key) {
            if (is_string($result[$key] ?? null) && trim($result[$key]) !== '') {
                return trim($result[$key]);
            }
        }

        $series = is_array($result['series'] ?? null) ? $result['series'] : [];
        if ($series !== []) {
            return match ($useCase) {
                'demand' => 'Draft demand forecast for the next four weeks. Review before anyone orders.',
                'consumption' => 'Draft consumption forecast for the next four weeks. History was not changed.',
                default => 'Draft forecast for the next four weeks. Inventory numbers stay as they are.',
            };
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $result
     * @return list<string>
     */
    private function linesFrom(?array $result): array
    {
        if ($result === null) {
            return [];
        }

        $lines = [];

        foreach ($result['series'] ?? [] as $point) {
            if (! is_array($point)) {
                continue;
            }
            $period = (string) ($point['period'] ?? '');
            $central = $point['central'] ?? null;
            $lower = $point['lower'] ?? null;
            $upper = $point['upper'] ?? null;
            if ($period === '' || ! is_numeric($central)) {
                continue;
            }
            $line = $period.': '.number_format((float) $central, 2);
            if (is_numeric($lower) && is_numeric($upper)) {
                $line .= ' ('.number_format((float) $lower, 2).'–'.number_format((float) $upper, 2).')';
            }
            $lines[] = $line;
        }

        foreach (['risks', 'patterns', 'assumptions'] as $key) {
            foreach ($result[$key] ?? [] as $item) {
                $text = $this->stringifyAdviceItem($item);
                if ($text !== null) {
                    $lines[] = ($key === 'assumptions' ? 'Assumption: ' : '').$text;
                }
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>|null  $result
     * @return list<array{period: string, central: float, lower: ?float, upper: ?float}>
     */
    public function seriesFrom(?array $result): array
    {
        if ($result === null) {
            return [];
        }

        $series = [];
        foreach ($result['series'] ?? [] as $point) {
            if (! is_array($point)) {
                continue;
            }
            $period = (string) ($point['period'] ?? '');
            $central = $point['central'] ?? null;
            if ($period === '' || ! is_numeric($central)) {
                continue;
            }
            $series[] = [
                'period' => $period,
                'central' => (float) $central,
                'lower' => is_numeric($point['lower'] ?? null) ? (float) $point['lower'] : null,
                'upper' => is_numeric($point['upper'] ?? null) ? (float) $point['upper'] : null,
            ];
        }

        return $series;
    }

    /**
     * @param  array<string, mixed>|null  $result
     * @return list<string>
     */
    private function listFrom(?array $result, string $key): array
    {
        if ($result === null) {
            return [];
        }

        $out = [];
        foreach ($result[$key] ?? [] as $item) {
            $text = $this->stringifyAdviceItem($item);
            if ($text !== null) {
                $out[] = $text;
            }
        }

        return $out;
    }

    private function stringifyAdviceItem(mixed $item): ?string
    {
        if (is_string($item) && trim($item) !== '') {
            return trim($item);
        }

        if (! is_array($item) || $item === []) {
            return null;
        }

        foreach (['text', 'summary', 'message', 'pattern', 'risk', 'name', 'item'] as $key) {
            if (is_string($item[$key] ?? null) && trim($item[$key]) !== '') {
                return trim($item[$key]);
            }
        }

        $encoded = json_encode($item);

        return is_string($encoded) ? $encoded : null;
    }

    private function timezoneFor(int $businessId, ?int $storeId): string
    {
        $branchId = null;
        if ($storeId) {
            $branchId = Store::query()
                ->where('business_id', $businessId)
                ->where('id', $storeId)
                ->value('branch_id');
        }

        try {
            return SharedTime::displayTimezone(
                (string) $businessId,
                $branchId ? (string) $branchId : null,
            );
        } catch (\Throwable) {
            return (string) config('app.timezone', 'Africa/Kampala');
        }
    }

    private function tenantId(int $businessId): ?string
    {
        $configured = trim((string) config('services.ai_gateway.tenant_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $uuid = Business::query()->where('id', $businessId)->value('uuid');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    private function itemLabel(int $businessId, ?int $itemId): ?string
    {
        if (! $itemId) {
            return null;
        }

        $item = Item::query()->where('business_id', $businessId)->find($itemId);
        if ($item === null) {
            return null;
        }

        return $item->code ? $item->name.' ('.$item->code.')' : (string) $item->name;
    }

    /**
     * @param  array<int|string>  $itemIds
     * @return list<int>
     */
    private function scopeIds(?int $itemId, array $itemIds): array
    {
        $ids = array_map('intval', $itemIds);
        if ($itemId) {
            $ids[] = (int) $itemId;
        }

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
    }

    /**
     * @param  list<int>  $scopeItemIds
     */
    private function focusPhrase(int $businessId, array $scopeItemIds): ?string
    {
        if ($scopeItemIds === []) {
            return null;
        }

        $labels = [];
        foreach (array_slice($scopeItemIds, 0, 12) as $id) {
            $labels[] = $this->itemLabel($businessId, $id) ?? ('item '.$id);
        }

        $phrase = implode(', ', $labels);
        $extra = count($scopeItemIds) - count($labels);
        if ($extra > 0) {
            $phrase .= ', and '.$extra.' more';
        }

        return count($scopeItemIds) === 1 ? $phrase : 'these items only: '.$phrase;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function awaitIfQueued(array $response, int $businessId): array
    {
        $attempts = 0;
        while (
            $attempts < 6
            && ($response['ok'] ?? false) !== true
            && in_array($response['errorCode'] ?? null, ['QUEUED', 'RUNNING'], true)
            && is_string($response['requestId'] ?? null)
            && $response['requestId'] !== ''
        ) {
            usleep(750000);
            $attempts++;
            $response = $this->client->fetch($response['requestId'], 'inventory', $this->tenantId($businessId));
        }

        if (($response['ok'] ?? false) !== true && in_array($response['errorCode'] ?? null, ['QUEUED', 'RUNNING'], true)) {
            $response['error'] = 'AI accepted the review and is still working. Wait a moment, then generate the order again.';
        }

        return $response;
    }

    private function isoWeekPeriod(Carbon $date): string
    {
        return sprintf('%d-W%02d', $date->isoWeekYear(), $date->isoWeek());
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = $item;
            }
        }

        return array_values($out);
    }

    /**
     * @return array{
     *     ok: bool,
     *     available: bool,
     *     capability: string,
     *     title: string,
     *     summary: null,
     *     lines: list<string>,
     *     warnings: list<string>,
     *     requiresHumanReview: bool,
     *     requestId: null,
     *     error: string,
     *     errorCode: null,
     *     details: null
     * }
     */
    private function localFailure(string $error, string $useCase): array
    {
        $meta = self::USE_CASES[$useCase] ?? null;

        return [
            'ok' => false,
            'available' => $this->isConfigured(),
            'capability' => $meta['capability'] ?? $useCase,
            'title' => $meta['label'] ?? 'AI advice',
            'summary' => null,
            'lines' => [],
            'series' => [],
            'assumptions' => [],
            'risks' => [],
            'patterns' => [],
            'warnings' => [],
            'requiresHumanReview' => true,
            'requestId' => null,
            'error' => $error,
            'errorCode' => null,
            'details' => null,
            'briefing' => null,
            'sent' => null,
            'received' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $sent
     * @param  array<string, mixed>  $received
     * @param  array<string, mixed>  $response
     */
    private function recordLog(
        string $useCase,
        string $capability,
        string $title,
        int $businessId,
        ?int $storeId,
        ?int $itemId,
        ?string $question,
        array $sent,
        array $received,
        array $response,
        ?string $summary,
    ): void {
        if (! Schema::hasTable('inventory_ai_advice_logs')) {
            return;
        }

        try {
            InventoryAiAdviceLog::query()->create([
                'business_id' => $businessId,
                'store_id' => $storeId,
                'item_id' => $itemId,
                'recorded_by_user_id' => Auth::id(),
                'use_case' => $useCase,
                'capability' => $capability,
                'title' => $title,
                'question' => $question,
                'request_id' => is_string($response['requestId'] ?? null) ? $response['requestId'] : null,
                'ok' => (bool) $response['ok'],
                'error_code' => is_string($response['errorCode'] ?? null) ? $response['errorCode'] : null,
                'error' => is_string($response['error'] ?? null) ? $response['error'] : null,
                'summary' => $summary,
                'request_payload' => $sent,
                'response_payload' => $received,
            ]);
        } catch (Throwable $e) {
            Log::warning('Could not store Inventory AI advice log: '.$e->getMessage());
        }
    }
}
