<?php

use App\Models\Business;
use App\Support\BusinessEntityCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every existing business an entity code.
 *
 * New businesses now generate one on create (Business::booted), but rows made
 * before that have NULL — and a null entity code is not cosmetic: it is the
 * tenant Main presents to the Clinical Module, and without it the request
 * carries a synthetic TENANT-{id} that Clinical has never heard of. Every
 * clinical read then comes back empty, which reads on screen as "this ward has
 * no beds" rather than "this facility is not mapped".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('businesses', 'entity_code')) {
            return;
        }

        Business::withTrashed()
            ->where(fn ($q) => $q->whereNull('entity_code')->orWhere('entity_code', ''))
            ->orderBy('id')
            ->each(function (Business $business) {
                $business->forceFill([
                    'entity_code' => BusinessEntityCode::generate((string) $business->name, $business->id),
                ])->saveQuietly();
            });
    }

    public function down(): void
    {
        // Deliberately irreversible: the codes are now referenced by the
        // Clinical Module as tenant ids and stamped on issued LPO numbers, so
        // clearing them would orphan live data to undo a backfill.
    }
};
