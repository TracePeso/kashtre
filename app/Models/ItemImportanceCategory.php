<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ItemImportanceCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_id',
        'name',
        'slug',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (ItemImportanceCategory $category): void {
            $category->uuid = (string) Str::uuid();

            if (blank($category->slug)) {
                $category->slug = static::uniqueSlugForBusiness(
                    (int) $category->business_id,
                    Str::slug((string) $category->name)
                );
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * @return array<string, string> slug => name
     */
    public static function optionsForBusiness(?int $businessId): array
    {
        if (! $businessId) {
            return [];
        }

        return static::query()
            ->forBusiness($businessId)
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();
    }

    public static function labelForSlug(?int $businessId, ?string $slug): ?string
    {
        if (! $slug) {
            return null;
        }

        if ($businessId) {
            $name = static::query()
                ->forBusiness($businessId)
                ->where('slug', $slug)
                ->value('name');

            if ($name) {
                return $name;
            }
        }

        return Item::legacyImportanceOptions()[$slug] ?? ucfirst(str_replace('_', ' ', $slug));
    }

    public static function uniqueSlugForBusiness(int $businessId, string $baseSlug): string
    {
        $slug = Str::limit($baseSlug, 64, '');
        $slug = $slug !== '' ? $slug : 'category';
        $candidate = $slug;
        $suffix = 2;

        while (static::withTrashed()
            ->where('business_id', $businessId)
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = Str::limit($slug.'-'.$suffix, 64, '');
            $suffix++;
        }

        return $candidate;
    }

    public static function ensureDefaultsForBusiness(int $businessId): void
    {
        foreach (Item::legacyImportanceOptions() as $slug => $name) {
            static::withTrashed()->firstOrCreate(
                ['business_id' => $businessId, 'slug' => $slug],
                ['name' => $name]
            );
        }
    }
}
