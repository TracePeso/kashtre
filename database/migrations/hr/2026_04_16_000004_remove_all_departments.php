<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Soft-delete all department type units
        DB::table('hr_organizational_units')
            ->where('type', 'department')
            ->update(['deleted_at' => now()]);
        
        // Soft-delete all descendants of departments
        DB::statement('
            UPDATE hr_organizational_units u1
            INNER JOIN (
                SELECT id FROM hr_organizational_units u2
                WHERE u2.type = \'department\'
            ) departments ON u1.parent_id = departments.id
            SET u1.deleted_at = NOW()
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore soft-deleted department units
        DB::statement('
            UPDATE hr_organizational_units 
            SET deleted_at = NULL 
            WHERE type = \'department\'
        ');
    }
};
