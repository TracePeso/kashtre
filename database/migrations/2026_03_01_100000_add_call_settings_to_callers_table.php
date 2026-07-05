<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: these columns are already defined in the base callers table migration.
    }

    public function down(): void
    {
        // No-op: nothing was added here.
    }
};
