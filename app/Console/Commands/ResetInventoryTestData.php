<?php

namespace App\Console\Commands;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteApproval;
use App\Models\GoodsReceivedNoteLine;
use App\Models\InventoryStockLevel;
use App\Models\InventoryStockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetInventoryTestData extends Command
{
    protected $signature = 'inventory:reset-test-data
                            {--business= : Business ID to reset (required unless --all)}
                            {--all : Reset all non-Kashtre businesses (business_id != 1)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clear GRNs, stock levels, and stock history for a fresh inventory test run';

    public function handle(): int
    {
        $businessIds = $this->resolveBusinessIds();

        if ($businessIds->isEmpty()) {
            $this->error('Specify --business=ID or use --all');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This deletes all GRNs and stock data for: '.$businessIds->implode(', ').'. Continue?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        foreach ($businessIds as $businessId) {
            $this->resetBusiness((int) $businessId);
        }

        $this->info('Inventory test data cleared. You can create a new GRN and test approval → monitor stock.');

        return self::SUCCESS;
    }

    private function resolveBusinessIds()
    {
        if ($this->option('all')) {
            return GoodsReceivedNote::query()
                ->distinct()
                ->where('business_id', '!=', 1)
                ->pluck('business_id')
                ->merge(
                    InventoryStockLevel::query()
                        ->where('business_id', '!=', 1)
                        ->distinct()
                        ->pluck('business_id')
                )
                ->unique()
                ->values();
        }

        $businessId = $this->option('business');

        if ($businessId === null || $businessId === '') {
            return collect();
        }

        return collect([(int) $businessId]);
    }

    private function resetBusiness(int $businessId): void
    {
        DB::transaction(function () use ($businessId) {
            $grnIds = GoodsReceivedNote::query()
                ->where('business_id', $businessId)
                ->pluck('id');

            $paths = GoodsReceivedNote::query()
                ->where('business_id', $businessId)
                ->whereNotNull('delivery_note_path')
                ->pluck('delivery_note_path');

            GoodsReceivedNoteApproval::query()
                ->whereIn('goods_received_note_id', $grnIds)
                ->delete();

            GoodsReceivedNoteLine::query()
                ->whereIn('goods_received_note_id', $grnIds)
                ->delete();

            GoodsReceivedNote::query()
                ->where('business_id', $businessId)
                ->delete();

            InventoryStockMovement::query()
                ->where('business_id', $businessId)
                ->delete();

            InventoryStockLevel::query()
                ->where('business_id', $businessId)
                ->delete();

            foreach ($paths as $path) {
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        });

        $this->line("Cleared inventory data for business #{$businessId}.");
    }
}
