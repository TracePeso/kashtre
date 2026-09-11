<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalWard extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_wards';

    protected $fillable = [
        'business_id',
        'branch_id',
        'building_wing',
        'ward_code',
        'ward_name',
        'client_space_id',
        'inventory_store_id',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'client_space_id' => 'integer',
        'inventory_store_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function beds()
    {
        return $this->hasMany(ClinicalBed::class, 'ward_id');
    }

    /**
     * Logical link only (different connection) — a plain lookup query, not
     * a SQL join, so it stays consistent with the no-cross-connection-join
     * rule even though Eloquent lets you call it like any relation.
     */
    public function clientSpace()
    {
        return $this->belongsTo(ClientSpace::class, 'client_space_id');
    }
}
