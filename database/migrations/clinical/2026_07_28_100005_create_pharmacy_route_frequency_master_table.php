<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clinical';

    public function up(): void
    {
        Schema::connection('clinical')->create('pharmacy_route_frequency_master', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->string('code', 32); // 'STAT', 'BID', 'Q4H', 'PO', 'IV'
            $table->enum('type', ['ROUTE', 'FREQUENCY']);
            $table->string('display_label', 128);
            $table->unsignedInteger('minute_interval')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'type', 'code'], 'uid_route_freq');
        });
    }

    public function down(): void
    {
        Schema::connection('clinical')->dropIfExists('pharmacy_route_frequency_master');
    }
};
