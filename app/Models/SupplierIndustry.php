<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupplierIndustry extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_id',
        'name',
        'description',
    ];

    protected $casts = [
        'uuid' => 'string',
        'business_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupplierIndustry $industry) {
            if (empty($industry->uuid)) {
                $industry->uuid = (string) Str::uuid();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subCategories(): HasMany
    {
        return $this->hasMany(SupplierSubCategory::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }
}
