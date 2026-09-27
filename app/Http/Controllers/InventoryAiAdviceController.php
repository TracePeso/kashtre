<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresInventoryModule;
use App\Models\InventoryAiAdviceLog;
use App\Support\InventoryBusinessContext;

class InventoryAiAdviceController extends Controller
{
    use RequiresInventoryModule;

    public function __construct()
    {
        $this->middleware($this->inventoryMiddleware(...));
    }

    public function index()
    {
        return view('inventory.ai.index');
    }

    public function show(InventoryAiAdviceLog $log)
    {
        if ((int) $log->business_id !== (int) InventoryBusinessContext::effectiveBusinessId()) {
            abort(404);
        }

        $log->load(['store:id,name', 'item:id,name,code', 'recordedBy:id,name']);

        return view('inventory.ai.show', [
            'log' => $log,
            'series' => $this->series($log),
            'assumptions' => $this->stringList($log->response_payload['result']['assumptions'] ?? $log->response_payload['result']['assumption'] ?? []),
            'risks' => $this->stringList($log->response_payload['result']['risks'] ?? []),
            'patterns' => $this->stringList($log->response_payload['result']['patterns'] ?? []),
        ]);
    }

    /**
     * @return list<array{period: string, central: float, lower: ?float, upper: ?float}>
     */
    private function series(InventoryAiAdviceLog $log): array
    {
        $points = $log->response_payload['result']['series'] ?? [];
        if (! is_array($points)) {
            return [];
        }

        $series = [];
        foreach ($points as $point) {
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
                $out[] = trim($item);
            }
        }

        return $out;
    }
}
