<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('hr_client_space_coverage_rules');
    }

    public function down(): void
    {
        // Coverage rules were removed from the product surface.
    }
};
