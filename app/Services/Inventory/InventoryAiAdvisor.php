<?php

namespace App\Services\Inventory;

use App\Models\InventoryDailyConsumption;
use App\Models\InventoryStockLevel;
use App\Models\Store;
use App\Services\AiGateway\CapabilityInvokeClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds inventory facts and asks the AI gateway for advice only.
 * Never creates orders, transfers, or write-offs.
 */
class InventoryAiAdvisor
{
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
        $history = $this->weeklyHistory($businessId, $storeId, $itemId, $historySource);
        $snapshot = $this->stockSnapshot($businessId, $storeId);

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
                'timezone' => 'Africa/Kampala',
                'history' => $history,
            ];

        $response = $this->client->invoke($capability, ['input' => $input], 'inventory');

        return [
            'ok' => $response['ok'],
            'available' => $response['available'],
            'capability' => $capability,
            'title' => $meta['label'],
            'summary' => $this->summaryFrom($response['result'] ?? null),
            'lines' => $this->linesFrom($response['result'] ?? null),
            'warnings' => $response['warnings'],
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
    public function weeklyHistory(int $businessId, ?int $storeId, ?int $itemId, string $source = 'demand'): array
    {
        $from = now()->subWeeks(11)->startOfWeek(Carbon::MONDAY)->toDateString();

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
            ->selectRaw("DATE_FORMAT(consumption_date, '%x-W%v') as period, SUM(quantity_suom) as value")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $rows->map(function ($row): array {
            $value = $row->value === null ? null : (float) $row->value;

            return [
                'period' => (string) $row->period,
                'value' => $value,
                'missing' => $value === null,
            ];
        })->all();
    }

    /**
     * @return list<array{name: string, code: ?string, on_hand: float, ma_15: float}>
     */
    public function stockSnapshot(int $businessId, ?int $storeId, int $limit = 8): array
    {
        return InventoryStockLevel::query()
            ->where('inventory_stock_levels.business_id', $businessId)
            ->when($storeId, fn ($q) => $q->where('inventory_stock_levels.store_id', $storeId))
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

        $itemBits = Collection::make($snapshot)
            ->take(6)
            ->map(function (array $item): string {
                $label = $item['code'] ? $item['name'].' ('.$item['code'].')' : $item['name'];

                return $label.': on-hand '.number_format($item['on_hand'], 1).', 15-day MA '.number_format($item['ma_15'], 2);
            })
            ->implode('; ');

        $base = match ($useCase) {
            'stockout' => 'Stockout risk for '.$scope.' over the next four weeks. Inventory rules remain authoritative.',
            'demand' => 'Weekly demand for '.$scope.' over the next four weeks. Do not create replenishment orders.',
            'consumption' => 'Weekly consumption for '.$scope.' over the next four weeks. Never modify source history.',
            'wastage' => 'Wastage and expiry patterns for '.$scope.'. Do not write off stock.',
            default => 'What should we check before ordering for '.$scope.'?',
        };

        if ($itemId) {
            $base .= ' Focus on the selected item.';
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
    private function summaryFrom(?array $result): ?string
    {
        if ($result === null) {
            return null;
        }

        foreach (['summary', 'narrative', 'answer'] as $key) {
            if (is_string($result[$key] ?? null) && trim($result[$key]) !== '') {
                return trim($result[$key]);
            }
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
