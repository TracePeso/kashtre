<?php

namespace App\Services\Inventory;

use App\Models\Business;
use App\Models\InventoryDailyConsumption;
use App\Models\InventoryStockLevel;
use App\Models\Item;
use App\Models\Store;
use App\Services\AiGateway\CapabilityInvokeClient;
use App\Support\SharedTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
            'needs_history' => true,
        ],
        'demand' => [
            'capability' => 'DEMAND_FORECAST',
            'label' => 'Ask for a demand forecast',
            'needs_history' => true,
        ],
        'consumption' => [
            'capability' => 'CONSUMPTION_FORECAST',
            'label' => 'Ask for a consumption forecast',
            'needs_history' => true,
        ],
        'wastage' => [
            'capability' => 'WASTAGE_PATTERN',
            'label' => 'Look for wastage patterns',
            'needs_history' => true,
        ],
        'ask' => [
            'capability' => 'AGENT_RUN',
            'label' => 'Ask before ordering',
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
    ): array {
        $meta = self::USE_CASES[$useCase] ?? null;
        if ($meta === null) {
            return $this->localFailure('Unknown AI task.', $useCase);
        }

        $capability = $meta['capability'];
        $historySource = $useCase === 'wastage' ? 'wastage' : 'demand';
        $timezone = $this->timezoneFor($businessId, $storeId);
        $history = $this->weeklyHistory($businessId, $storeId, $itemId, $historySource, $timezone);
        $snapshot = $this->stockSnapshot($businessId, $storeId, $itemId);

        if ($meta['needs_history'] && count($this->usableHistory($history)) < 3) {
            return $this->localFailure(
                'Need at least three weeks of history before AI can advise. Inventory numbers stay as they are.',
                $useCase,
            );
        }

        $measure = $this->measure($useCase, $businessId, $storeId, $itemId, $snapshot, $question);
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

        $response = $this->client->invoke(
            $capability,
            $input,
            'inventory',
            null,
            $this->tenantId($businessId),
        );

        $result = is_array($response['result'] ?? null) ? $response['result'] : null;
        $warnings = $response['warnings'];
        foreach ($this->stringList($result['warnings'] ?? []) as $warning) {
            $warnings[] = $warning;
        }

        return [
            'ok' => $response['ok'],
            'available' => $response['available'],
            'capability' => $capability,
            'title' => $meta['label'],
            'summary' => $this->summaryFrom($result, $useCase),
            'lines' => $this->linesFrom($result),
            'warnings' => array_values(array_unique($warnings)),
            'requiresHumanReview' => true,
            'requestId' => $response['requestId'],
            'error' => $response['error'],
            'errorCode' => $response['errorCode'],
            'details' => $response['details'],
        ];
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
    ): array {
        $timezone ??= $this->timezoneFor($businessId, $storeId);
        $end = Carbon::now($timezone)->startOfWeek(Carbon::MONDAY);
        $from = $end->copy()->subWeeks(self::HISTORY_WEEKS - 1)->toDateString();

        $query = InventoryDailyConsumption::query()
            ->where('business_id', $businessId)
            ->whereDate('consumption_date', '>=', $from)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId));

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
    public function stockSnapshot(int $businessId, ?int $storeId, ?int $itemId = null, int $limit = 8): array
    {
        return InventoryStockLevel::query()
            ->where('inventory_stock_levels.business_id', $businessId)
            ->when($storeId, fn ($q) => $q->where('inventory_stock_levels.store_id', $storeId))
            ->when($itemId, fn ($q) => $q->where('inventory_stock_levels.item_id', $itemId))
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
        ?int $itemId,
        array $snapshot,
        ?string $question,
    ): string {
        $storeName = $storeId ? Store::query()->where('business_id', $businessId)->find($storeId)?->name : null;
        $scope = $storeName ? 'store '.$storeName : 'this organisation';
        $itemName = $this->itemLabel($businessId, $itemId);

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

        if ($itemName !== null) {
            $base .= ' Focus on '.$itemName.'.';
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
            $horizon = is_string($result['horizon'] ?? null) && $result['horizon'] !== ''
                ? $result['horizon']
                : 'the next four weeks';

            return match ($useCase) {
                'demand' => 'Draft demand forecast for '.$horizon.'. Review before anyone orders.',
                'consumption' => 'Draft consumption forecast for '.$horizon.'. History was not changed.',
                default => 'Draft forecast for '.$horizon.'. Inventory numbers stay as they are.',
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

    private function stringifyAdviceItem(mixed $item): ?string
    {
        if (is_string($item) && trim($item) !== '') {
            return trim($item);
        }

        if (! is_array($item)) {
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
            return 'the selected item';
        }

        return $item->code ? $item->name.' ('.$item->code.')' : (string) $item->name;
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
            'warnings' => [],
            'requiresHumanReview' => true,
            'requestId' => null,
            'error' => $error,
            'errorCode' => null,
            'details' => null,
        ];
    }
}
