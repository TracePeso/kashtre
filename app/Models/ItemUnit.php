<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ItemUnit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_id',
        'name',
        'description',
    ];

    protected static function booted()
    {
        static::creating(function ($unit) {
            $unit->uuid = (string) Str::uuid();
        });

        static::saving(function (self $unit) {
            $name = trim((string) $unit->name);
            if ($name === '' || ! $unit->business_id) {
                return;
            }

            $exists = static::query()
                ->where('business_id', $unit->business_id)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->when($unit->exists, fn ($q) => $q->where('id', '!=', $unit->id))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => 'This business already has an item unit named "'.$name.'". Reuse the existing one on items instead of creating a duplicate.',
                ]);
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
