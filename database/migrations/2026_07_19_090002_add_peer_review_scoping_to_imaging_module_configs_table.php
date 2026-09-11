<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imaging_module_configs', function (Blueprint $table) {
            // Null/empty on both = today's behavior (every modality eligible,
            // any permitted user can review) — additive, no data migration needed.
            $table->json('peer_review_eligible_modalities')->nullable()->after('peer_review_rate');
            $table->json('peer_review_reviewer_pool_user_ids')->nullable()->after('peer_review_eligible_modalities');
        });
    }

    public function down(): void
    {
        Schema::table('imaging_module_configs', function (Blueprint $table) {
            $table->dropColumn(['peer_review_eligible_modalities', 'peer_review_reviewer_pool_user_ids']);
        });
    }
};
