<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Store extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_id',
        'branch_id',
        'parent_id',
        'name',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            if (empty($store->uuid)) {
                $store->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (Store $store) {
            $store->applyParentHierarchy();
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Store::class, 'parent_id');
    }

    public function isChild(): bool
    {
        return $this->parent_id !== null;
    }

    public function isParent(): bool
    {
        return $this->parent_id === null;
    }

    public function depth(): int
    {
        if (! $this->parent_id) {
            return 0;
        }

        $parent = $this->relationLoaded('parent') ? $this->parent : static::query()->find($this->parent_id);

        return $parent ? 1 + $parent->depth() : 0;
    }

    public function hierarchyLabel(): string
    {
        return match ($this->depth()) {
            0 => 'Main store',
            1 => 'Branch store',
            2 => 'Unit store',
            default => 'Store',
        };
    }

    /**
     * @return array<int, int>
     */
    public static function descendantIds(int $storeId): array
    {
        $ids = [$storeId];

        foreach (static::query()->where('parent_id', $storeId)->pluck('id') as $childId) {
            $ids = array_merge($ids, static::descendantIds((int) $childId));
        }

        return array_values(array_unique($ids));
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForSelect(int $businessId): array
    {
        return static::query()
            ->forBusiness($businessId)
            ->with('parent')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Store $store) => [
                $store->id => $store->selectLabel(),
            ])
            ->all();
    }

    public function selectLabel(): string
    {
        if ($this->isChild() && $this->relationLoaded('parent') && $this->parent) {
            return '↳ ' . $this->name . ' (' . $this->parent->name . ')';
        }

        if ($this->isChild() && $this->parent_id) {
            $parentName = static::query()->whereKey($this->parent_id)->value('name');

            return '↳ ' . $this->name . ($parentName ? ' (' . $parentName . ')' : '');
        }

        return $this->name;
    }

    protected function applyParentHierarchy(): void
    {
        if (! $this->parent_id) {
            return;
        }

        if ((int) $this->parent_id === (int) $this->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A store cannot be its own parent.',
            ]);
        }

        $parent = static::query()->find($this->parent_id);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent store is invalid.',
            ]);
        }

        if ($parent->parent_id !== null) {
            $grandparent = static::query()->find($parent->parent_id);

            if ($grandparent && $grandparent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Stores support up to three levels: Main → Branch → Unit.',
                ]);
            }
        }

        if ($this->business_id && (int) $this->business_id !== (int) $parent->business_id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent store must belong to the same business.',
            ]);
        }

        $this->business_id = $parent->business_id;
        $this->branch_id = $parent->branch_id;

        $ancestorId = $parent->id;
        while ($ancestorId) {
            if ((int) $ancestorId === (int) $this->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Invalid parent store hierarchy.',
                ]);
            }
            $ancestorId = static::query()->whereKey($ancestorId)->value('parent_id');
        }
    }
}
