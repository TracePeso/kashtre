<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class GrnBulkImportService
{
    /** @var array<int, string> */
    public const TEMPLATE_HEADERS = [
        'item_code',
        'item_name',
        'quantity',
        'batch_number',
        'expiry_date (YYYY-MM-DD)',
        'duom',
        'purchase_price',
        'sale_units_per_delivery (e.g. 100)',
    ];

    public const EXPIRY_DATE_FORMAT = 'YYYY-MM-DD';

    public const EXPIRY_DATE_EXAMPLE = '2026-12-31';

    /**
     * @return array{lines: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function parseUpload(UploadedFile $file, int $businessId, ?int $supplierId = null): array
    {
        $items = $this->itemsForBusiness($businessId, $supplierId);
        $itemsByCode = $items->keyBy(fn (Item $item) => strtolower(trim((string) $item->code)));

        $unitNames = ItemUnit::query()
            ->where('business_id', $businessId)
            ->pluck('name')
            ->map(fn (string $name) => strtolower(trim($name)))
            ->flip();

        $lines = [];
        $errors = [];
        $seenItemIds = [];
        $rowNumber = 0;

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [
                'lines' => [],
                'errors' => ['Could not read the uploaded file.'],
            ];
        }

        $headerMap = null;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isBlankRow($row)) {
                continue;
            }

            $firstCell = trim((string) ($row[0] ?? ''));

            if (str_starts_with($firstCell, '#')) {
                continue;
            }

            if ($headerMap === null) {
                $headerMap = $this->mapHeaders($row);

                if ($headerMap === null) {
                    fclose($handle);

                    return [
                        'lines' => [],
                        'errors' => ['Row '.$rowNumber.': Unrecognised header row. Download the template and use those column names.'],
                    ];
                }

                continue;
            }

            $data = $this->rowToAssoc($row, $headerMap);
            $itemCode = trim((string) ($data['item_code'] ?? ''));

            if ($itemCode === '') {
                continue;
            }

            $item = $itemsByCode->get(strtolower($itemCode));

            if (! $item) {
                $errors[] = 'Row '.$rowNumber.': item_code "'.$itemCode.'" was not found'.($supplierId ? ' for the selected supplier' : '').'.';

                continue;
            }

            $quantityRaw = trim((string) ($data['quantity'] ?? ''));

            if ($quantityRaw === '' || $quantityRaw === '0') {
                continue;
            }

            if (isset($seenItemIds[$item->id])) {
                $errors[] = 'Row '.$rowNumber.': item "'.$itemCode.'" appears more than once. Keep one row per item.';

                continue;
            }

            $quantity = $this->parsePositiveNumber($quantityRaw);

            if ($quantity === null) {
                $errors[] = 'Row '.$rowNumber.': quantity must be a number greater than zero.';

                continue;
            }

            $defaults = $this->defaultLineFromItem($item);

            $duom = trim((string) ($data['duom'] ?? ''));

            if ($duom === '') {
                $duom = (string) $defaults['duom'];
            }

            if ($duom === '' || ! $unitNames->has(strtolower($duom))) {
                $errors[] = 'Row '.$rowNumber.': duom "'.$duom.'" is not a valid delivery unit for your organisation.';

                continue;
            }

            $purchasePriceRaw = trim((string) ($data['purchase_price'] ?? ''));
            $purchasePrice = $purchasePriceRaw !== ''
                ? $this->parseNonNegativeNumber($purchasePriceRaw)
                : (float) $defaults['purchase_price'];

            if ($purchasePrice === null) {
                $errors[] = 'Row '.$rowNumber.': purchase_price must be zero or greater.';

                continue;
            }

            $conversionRaw = trim((string) ($data['sale_units_per_purchase_unit'] ?? ''));
            $conversion = $conversionRaw !== ''
                ? $this->parsePositiveNumber($conversionRaw)
                : (float) $defaults['conversion'];

            if ($conversion === null) {
                $errors[] = 'Row '.$rowNumber.': sale units per delivery must be a number greater than zero.';

                continue;
            }

            $suom = $item->itemUnit?->name;

            if (! $suom) {
                $errors[] = 'Row '.$rowNumber.': item "'.$itemCode.'" has no sale unit configured in the item master.';

                continue;
            }

            $expiryDate = $this->parseExpiryDate(trim((string) ($data['expiry_date'] ?? '')));

            if ($expiryDate === null) {
                $errors[] = 'Row '.$rowNumber.': expiry_date must be a valid date in '.self::EXPIRY_DATE_FORMAT.' format (e.g. '.self::EXPIRY_DATE_EXAMPLE.').';

                continue;
            }

            $seenItemIds[$item->id] = true;

            $lines[] = [
                'item_id' => (string) $item->id,
                'inventory_order_line_id' => '',
                'suom' => $suom,
                'duom' => $duom,
                'item_suom' => $suom,
                'quantity' => $quantity,
                'batch_number' => trim((string) ($data['batch_number'] ?? '')),
                'expiry_date' => $expiryDate,
                'purchase_price' => $purchasePrice,
                'conversion' => $conversion,
            ];
        }

        fclose($handle);

        if ($headerMap === null) {
            return [
                'lines' => [],
                'errors' => ['The file is empty or has no header row.'],
            ];
        }

        if ($lines === [] && $errors === []) {
            $errors[] = 'No received quantities found. Enter a quantity greater than zero for each item you received.';
        }

        return [
            'lines' => $lines,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalogueLines(int $businessId, ?int $supplierId = null): array
    {
        return $this->itemsForBusiness($businessId, $supplierId)
            ->map(fn (Item $item) => $this->defaultLineFromItem($item))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultLineFromItem(Item $item): array
    {
        $conversion = (float) ($item->suom_per_ouom ?? 0) > 0
            ? (float) $item->suom_per_ouom
            : 1.0;

        $suom = $item->itemUnit?->name ?? '';
        $duom = $item->orderUnit?->name ?? $suom;

        return [
            'item_id' => (string) $item->id,
            'inventory_order_line_id' => '',
            'suom' => $suom,
            'duom' => $duom,
            'item_suom' => $suom,
            'quantity' => 1,
            'batch_number' => '',
            'expiry_date' => '',
            'purchase_price' => $item->purchasePricePerOuom(),
            'conversion' => $conversion,
        ];
    }

    /**
     * @param  array<int>|null  $itemIds
     * @return Collection<int, Item>
     */
    public function itemsForBusiness(int $businessId, ?int $supplierId = null, ?array $itemIds = null): Collection
    {
        $query = Item::query()
            ->where('business_id', $businessId)
            ->where('type', 'good')
            ->with(['itemUnit', 'orderUnit'])
            ->orderBy('name');

        if ($supplierId) {
            $supplier = Supplier::query()
                ->where('business_id', $businessId)
                ->whereKey($supplierId)
                ->with('items:id')
                ->first();

            if ($supplier && $supplier->items->isNotEmpty()) {
                $query->whereIn('id', $supplier->items->pluck('id'));
            }
        }

        if ($itemIds !== null && $itemIds !== []) {
            $query->whereIn('id', array_map('intval', $itemIds));
        }

        return $query->get();
    }

    /**
     * @param  array<int>|null  $itemIds
     * @return array<int, array<int, string>>
     */
    public function templateRows(int $businessId, ?int $supplierId = null, ?array $itemIds = null): array
    {
        $items = $this->itemsForBusiness($businessId, $supplierId, $itemIds);

        $rows = [
            ['# Fill quantity (and batch/expiry if needed) only for items received.'],
            ['# Leave quantity blank to skip a row on upload. Other columns are pre-filled from the item master.'],
            ['# expiry_date is optional. Use '.self::EXPIRY_DATE_FORMAT.' only (example: '.self::EXPIRY_DATE_EXAMPLE.').'],
            ['# sale_units_per_delivery: how many sale units are in one delivery unit (e.g. 100 tablets per box).'],
            self::TEMPLATE_HEADERS,
        ];

        foreach ($items as $item) {
            $defaults = $this->defaultLineFromItem($item);

            $rows[] = [
                $item->code ?? '',
                $item->name ?? '',
                '',
                '',
                '',
                (string) $defaults['duom'],
                (string) $defaults['purchase_price'],
                (string) $defaults['conversion'],
            ];
        }

        if ($items->isEmpty()) {
            $rows[] = ['', '', '', '', '', '', '', ''];
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array<string, int>|null
     */
    private function mapHeaders(array $row): ?array
    {
        $normalized = [];

        foreach ($row as $index => $cell) {
            $key = $this->normalizeHeaderKey((string) $cell);

            if ($key !== '') {
                $normalized[$key] = $index;
            }
        }

        $required = ['item_code', 'quantity'];

        foreach ($required as $column) {
            if (! array_key_exists($column, $normalized)) {
                return null;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $headerMap
     * @return array<string, string|null>
     */
    private function rowToAssoc(array $row, array $headerMap): array
    {
        $data = [];

        foreach ($headerMap as $key => $index) {
            $data[$key] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        return $data;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parsePositiveNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number > 0 ? $number : null;
    }

    private function parseNonNegativeNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number >= 0 ? $number : null;
    }

    private function normalizeHeaderKey(string $cell): string
    {
        $key = strtolower(trim($cell));

        if ($key === '' || str_starts_with($key, '#')) {
            return '';
        }

        if ($key === 'expiry_date' || str_starts_with($key, 'expiry_date (')) {
            return 'expiry_date';
        }

        if ($key === 'sale_units_per_purchase_unit'
            || str_starts_with($key, 'sale_units_per_delivery')
            || str_starts_with($key, 'units_per_delivery')) {
            return 'sale_units_per_purchase_unit';
        }

        return $key;
    }

    /**
     * @return string|null Empty string when blank, Y-m-d when valid, null when invalid.
     */
    private function parseExpiryDate(string $value): ?string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            if ($date !== false && $date->format('Y-m-d') === $value) {
                return $value;
            }
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }
}
