<?php

use App\Models\GoodsReceivedNote;
use App\Services\GoodsReceivedNoteService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->timestamp('stock_applied_at')->nullable()->after('approved_at');
        });

        $service = app(GoodsReceivedNoteService::class);

        GoodsReceivedNote::query()
            ->where('status', GoodsReceivedNote::STATUS_APPROVED)
            ->whereNull('stock_applied_at')
            ->with('lines')
            ->each(function (GoodsReceivedNote $grn) use ($service) {
                $service->applyStockIfNeeded($grn);
            });
    }

    public function down(): void
    {
        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->dropColumn('stock_applied_at');
        });
    }
};
