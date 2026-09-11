<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalConsumptionEvent extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_consumption_events';

    public $timestamps = false;

    const TOKEN_MEDICATION_ADMINISTERED = 'MEDICATION_ADMINISTERED';
    const TOKEN_MEDICATION_WASTED = 'MEDICATION_WASTED';
    const TOKEN_NON_APPROVED_FLOOR_STOCK_USAGE = 'NON_APPROVED_FLOOR_STOCK_USAGE';
    const TOKEN_CRASH_CART_CONSUMPTION = 'CRASH_CART_CONSUMPTION';
    const TOKEN_LAB_CONSUMPTION_FACT = 'LAB_CONSUMPTION_FACT';

    const SCENARIO_A_APPROVED_POOL = 'SCENARIO_A_APPROVED_POOL';
    const SCENARIO_B_NON_APPROVED_FLOOR_STOCK = 'SCENARIO_B_NON_APPROVED_FLOOR_STOCK';
    const SCENARIO_C_ADMINISTRATIVE = 'SCENARIO_C_ADMINISTRATIVE';
    const SCENARIO_D_CRASH_CART = 'SCENARIO_D_CRASH_CART';
    const SCENARIO_WASTAGE = 'WASTAGE';

    protected $fillable = [
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'fact_token',
        'usage_context',
        'item_code',
        'quantity',
        'inventory_store_id',
        'reconciliation_scenario',
        'physical_stock_reduced',
        'approved_pool_reduced',
        'billing_triggered',
        'recorded_by_user_id',
        'occurred_at',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'quantity' => 'decimal:4',
        'inventory_store_id' => 'integer',
        'physical_stock_reduced' => 'boolean',
        'approved_pool_reduced' => 'boolean',
        'billing_triggered' => 'boolean',
        'recorded_by_user_id' => 'integer',
        'occurred_at' => 'datetime',
    ];
}
