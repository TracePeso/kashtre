<?php

namespace App\Support;

use App\Models\Business;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class DocumentViewData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function merge(array $data, ?Business $business = null): array
    {
        $business ??= self::resolveBusiness($data);

        if ($business !== null && ! array_key_exists('branding', $data)) {
            $data['branding'] = BusinessBranding::for($business);
        }

        if (! array_key_exists('generatedAt', $data)) {
            $data['generatedAt'] = now();
        }

        if (! array_key_exists('showKashtreCredit', $data)) {
            $data['showKashtreCredit'] = true;
        }

        if (! array_key_exists('forPdf', $data)) {
            $data['forPdf'] = false;
        }

        $data = self::applyDocumentTitles($data);

        return $data;
    }

    /**
     * @return array<int, string>
     */
    public static function documentViewNames(): array
    {
        return [
            'layouts.pdf',
            'layouts.document',
            'invoices.print',
            'quotations.print',
            'inventory.orders.pdf.rfq',
            'inventory.purchase-orders.pdf.lpo',
            'inventory.consumption.pdf',
            'pdf.transaction_receipt',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveBusiness(array $data): ?Business
    {
        if (($data['business'] ?? null) instanceof Business) {
            return $data['business'];
        }

        foreach (['invoice', 'quotation', 'order', 'po', 'goodsReceivedNote', 'transaction'] as $key) {
            $model = $data[$key] ?? null;

            if (! $model instanceof Model) {
                continue;
            }

            if ($model->relationLoaded('business')) {
                $related = $model->getRelation('business');

                if ($related instanceof Business) {
                    return $related;
                }
            }

            if (! empty($model->business_id)) {
                $loaded = $model->business;

                if ($loaded instanceof Business) {
                    return $loaded;
                }

                return Business::query()->find((int) $model->business_id);
            }
        }

        if (isset($data['transaction']) && $data['transaction']->member) {
            $member = $data['transaction']->member;

            if (! empty($member->business_id)) {
                return Business::query()->find((int) $member->business_id);
            }
        }

        if (! empty($data['business_id'])) {
            return Business::query()->find((int) $data['business_id']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function applyDocumentTitles(array $data): array
    {
        if (! isset($data['documentTitle']) && isset($data['invoice'])) {
            $data['documentTitle'] = 'INVOICE';
            $data['documentSubtitle'] = $data['documentSubtitle'] ?? $data['invoice']->invoice_number;
            $data['branchName'] = $data['branchName'] ?? $data['invoice']->branch?->name;
            $data['footerExtraLines'] = $data['footerExtraLines'] ?? [
                'Thank you for your business!',
                'Invoice: '.$data['invoice']->invoice_number,
            ];
        }

        if (! isset($data['documentTitle']) && isset($data['quotation'])) {
            $data['documentTitle'] = 'QUOTATION';
            $data['documentSubtitle'] = $data['documentSubtitle'] ?? $data['quotation']->quotation_number;
            $data['headerLayout'] = $data['headerLayout'] ?? 'centered';
            $data['footerExtraLines'] = $data['footerExtraLines'] ?? [
                'Thank you for your business!',
                'Quotation: '.$data['quotation']->quotation_number,
            ];
        }

        if (! isset($data['documentTitle']) && isset($data['order'])) {
            $order = $data['order'];
            $data['documentTitle'] = $order->isDraft() || $order->isPendingApproval()
                ? 'Purchase Request'
                : 'Request for Quotation';
            $data['documentSubtitle'] = $data['documentSubtitle'] ?? $order->order_number.' · '.$order->statusLabel();
        }

        if (! isset($data['documentTitle']) && isset($data['po'])) {
            $data['documentTitle'] = 'Local Purchase Order';
            $data['documentSubtitle'] = $data['documentSubtitle'] ?? $data['po']->po_number.' · '.$data['po']->statusLabel();
        }

        if (! isset($data['documentTitle']) && isset($data['meta']) && is_array($data['meta'])) {
            $data['documentTitle'] = 'Inventory Consumption';
            $data['documentSubtitle'] = $data['documentSubtitle'] ?? ($data['meta']['period_label'] ?? null);
        }

        if (! isset($data['documentTitle']) && isset($data['transaction'])) {
            $data['documentTitle'] = 'Transaction Receipt';
        }

        return $data;
    }

    public static function generatedAtLabel(?CarbonInterface $generatedAt = null): string
    {
        return 'Generated: '.($generatedAt ?? now())->format('d M Y H:i');
    }
}
