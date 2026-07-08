<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: client_spaces is already created by the earlier migration.
    }

    public function down(): void
    {
        // No-op: nothing was created here.
    }
};
