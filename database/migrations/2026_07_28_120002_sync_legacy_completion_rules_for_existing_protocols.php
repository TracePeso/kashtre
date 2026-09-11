<?php

use App\Models\ImagingProtocol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * RIS Amendment v2.6, Chunk 5: one-time backfill — every protocol
     * created before this chunk shipped already has a real workflow
     * (Chunk 2's own backfill guaranteed that), but ImagingProtocol::booted()'s
     * new saved() hook only fires on FUTURE saves. This runs it once for
     * everything that already exists, same idea as Chunk 2's own backfill
     * migration for the workflows themselves.
     */
    public function up(): void
    {
        ImagingProtocol::query()->each(function (ImagingProtocol $protocol) {
            $protocol->syncLegacyCompletionRules();
        });
    }

    public function down(): void
    {
        //
    }
};
