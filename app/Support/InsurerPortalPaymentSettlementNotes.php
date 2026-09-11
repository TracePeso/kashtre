<?php

namespace App\Support;

use App\Models\ThirdPartyPayer;
use App\Models\ThirdPartyPayerBalanceHistory;

/**
 * JSON stored on payer credit notes for insurer-portal payments (item/invoice display).
 */
final class InsurerPortalPaymentSettlementNotes
{
    public const KEY = 'insurer_portal_payment';

    /**
     * @param  array<int, array<string, mixed>>  $settledLines
     */
    public static function encode(array $settledLines, float $serviceCharge = 0): string
    {
        return json_encode([
            self::KEY => true,
            'settled_lines' => $settledLines,
            'service_charge' => round($serviceCharge, 2),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{insurer_portal_payment: bool, settled_lines: array<int, array<string, mixed>>, service_charge: float}
     */
    public static function decode(?string $notes): array
    {
        if ($notes === null || trim($notes) === '') {
            return ['insurer_portal_payment' => false, 'settled_lines' => [], 'service_charge' => 0.0];
        }

        $trim = trim($notes);
        if (! str_starts_with($trim, '{')) {
            return ['insurer_portal_payment' => false, 'settled_lines' => [], 'service_charge' => 0.0];
        }

        try {
            $data = json_decode($trim, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['insurer_portal_payment' => false, 'settled_lines' => [], 'service_charge' => 0.0];
        }

        if (! is_array($data) || empty($data[self::KEY])) {
            return ['insurer_portal_payment' => false, 'settled_lines' => [], 'service_charge' => 0.0];
        }

        return [
            'insurer_portal_payment' => true,
            'settled_lines' => is_array($data['settled_lines'] ?? null) ? $data['settled_lines'] : [],
            'service_charge' => (float) ($data['service_charge'] ?? 0),
        ];
    }

    /**
     * @param  array<int, int>  $historyIds
     * @return array<int, array<string, mixed>>
     */
    public static function linesFromSettledHistories(array $historyIds, ThirdPartyPayer $payer): array
    {
        if ($historyIds === []) {
            return [];
        }

        return ThirdPartyPayerBalanceHistory::query()
            ->where('third_party_payer_id', $payer->id)
            ->whereIn('id', $historyIds)
            ->with(['invoice', 'client'])
            ->orderBy('id')
            ->get()
            ->map(function (ThirdPartyPayerBalanceHistory $history) use ($payer) {
                $invoice = $history->invoice;
                $lineLabel = (string) ($history->description ?? 'Outstanding item');
                if ($invoice && $payer) {
                    $matched = \App\Services\ThirdPartyPayerStatementPresenter::matchInvoiceItemLabel(
                        $lineLabel,
                        $invoice
                    );
                    if ($matched) {
                        $lineLabel = $matched;
                    }
                }

                return [
                    'history_id' => (int) $history->id,
                    'description' => $lineLabel,
                    'detail_description' => $history->description,
                    'amount' => round(abs((float) $history->change_amount), 2),
                    'invoice_id' => $history->invoice_id,
                    'invoice_number' => $invoice?->invoice_number,
                    'client' => $history->client ? [
                        'name' => $history->client->name,
                        'client_id' => $history->client->client_id,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }
}
