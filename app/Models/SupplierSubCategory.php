<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupplierSubCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_id',
        'supplier_industry_id',
        'name',
        'description',
    ];

    protected $casts = [
        'uuid' => 'string',
        'business_id' => 'integer',
        'supplier_industry_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupplierSubCategory $subCategory) {
            if (empty($subCategory->uuid)) {
                $subCategory->uuid = (string) Str::uuid();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(SupplierIndustry::class, 'supplier_industry_id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }
}
