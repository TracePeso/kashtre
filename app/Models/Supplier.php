<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_id',
        'supplier_industry_id',
        'supplier_sub_category_id',
        'linked_business_id',
        'name',
        'email',
        'phone',
        'description',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            $user->uuid = (string) Str::uuid();
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function industry()
    {
        return $this->belongsTo(SupplierIndustry::class, 'supplier_industry_id');
    }

    public function subCategory()
    {
        return $this->belongsTo(SupplierSubCategory::class, 'supplier_sub_category_id');
    }

    public function linkedBusiness()
    {
        return $this->belongsTo(Business::class, 'linked_business_id');
    }

    public function isKashtreEntitySupplier(): bool
    {
        return $this->linked_business_id !== null;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'supplier_item')->withTimestamps();
    }

    public function suppliesItem(int $itemId): bool
    {
        if ($this->items()->count() === 0) {
            return true;
        }

        return $this->items()->where('items.id', $itemId)->exists();
    }
}
