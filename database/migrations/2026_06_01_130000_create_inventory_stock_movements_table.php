<?php

use App\Models\GoodsReceivedNote;
use App\Models\InventoryStockMovement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('movement_type', 32);
            $table->decimal('quantity_delta', 14, 4);
            $table->decimal('balance_after', 14, 4);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->foreignId('goods_received_note_id')->nullable()->constrained('goods_received_notes')->nullOnDelete();
            $table->foreignId('goods_received_note_line_id')->nullable()->constrained('goods_received_note_lines')->nullOnDelete();
            $table->string('reference_label')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['business_id', 'item_id', 'occurred_at']);
            $table->index(['goods_received_note_id']);
        });

        GoodsReceivedNote::query()
            ->where('status', GoodsReceivedNote::STATUS_APPROVED)
            ->whereNotNull('stock_applied_at')
            ->with(['lines', 'entryBy'])
            ->orderBy('stock_applied_at')
            ->each(function (GoodsReceivedNote $grn) {
                foreach ($grn->lines as $line) {
                    $delta = (float) $line->sale_units_purchased;

                    if ($delta <= 0) {
                        continue;
                    }

                    InventoryStockMovement::query()->firstOrCreate(
                        [
                            'goods_received_note_line_id' => $line->id,
                            'movement_type' => InventoryStockMovement::TYPE_GRN_RECEIPT,
                        ],
                        [
                            'business_id' => $grn->business_id,
                            'item_id' => $line->item_id,
                            'quantity_delta' => $delta,
                            'balance_after' => 0,
                            'unit_price' => $line->purchase_price,
                            'goods_received_note_id' => $grn->id,
                            'reference_label' => $grn->grn_number,
                            'recorded_by_user_id' => $grn->entry_by_user_id,
                            'occurred_at' => $grn->stock_applied_at ?? $grn->approved_at ?? $grn->updated_at,
                        ]
                    );
                }
            });

        InventoryStockMovement::query()
            ->select('item_id', 'business_id')
            ->distinct()
            ->orderBy('item_id')
            ->get()
            ->each(function ($row) {
                $running = 0.0;

                InventoryStockMovement::query()
                    ->where('business_id', $row->business_id)
                    ->where('item_id', $row->item_id)
                    ->orderBy('occurred_at')
                    ->orderBy('id')
                    ->each(function (InventoryStockMovement $movement) use (&$running) {
                        $running += (float) $movement->quantity_delta;
                        $movement->update(['balance_after' => $running]);
                    });
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
    }
};
