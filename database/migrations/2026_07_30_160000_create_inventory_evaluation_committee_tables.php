<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Superseded by 2026_07_30_160001_ensure_inventory_evaluation_committee_tables.php
        // when the first run partially created tables before FK name limits were fixed.
    }

    public function down(): void
    {
        //
    }
};
