<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientSpace extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_id',
        'name',
        'description',
        'branch_id',
        'space_head_id',
        'deputy_space_head_id',
        'alternate_space_head_id',
        'is_default',
    ];

    protected $casts = [
        'uuid' => 'string',
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'space_head_id' => 'integer',
        'deputy_space_head_id' => 'integer',
        'alternate_space_head_id' => 'integer',
        'is_default' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function spaceHead()
    {
        return $this->belongsTo(User::class, 'space_head_id');
    }

    public function deputySpaceHead()
    {
        return $this->belongsTo(User::class, 'deputy_space_head_id');
    }

    public function alternateSpaceHead()
    {
        return $this->belongsTo(User::class, 'alternate_space_head_id');
    }

    public function storeAssignment()
    {
        return $this->hasOne(ClientSpaceStoreAssignment::class);
    }

    public function endStore()
    {
        return $this->hasOneThrough(
            Store::class,
            ClientSpaceStoreAssignment::class,
            'client_space_id',
            'id',
            'id',
            'store_id'
        );
    }

    /**
     * Active End Store routing for fulfillment queue ingestion.
     */
    public function resolveEndStoreAssignment(): ?ClientSpaceStoreAssignment
    {
        return ClientSpaceStoreAssignment::resolveForClientSpace((int) $this->id);
    }

    protected static function booted()
    {
        static::creating(function ($clientSpace) {
            $clientSpace->uuid = (string) Str::uuid();
        });

        static::saving(function (self $clientSpace) {
            if (! $clientSpace->is_default) {
                return;
            }

            // One default Client Space per business (POS auto-select).
            static::query()
                ->where('business_id', $clientSpace->business_id)
                ->when($clientSpace->exists, fn ($q) => $q->where('id', '!=', $clientSpace->id))
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }
    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
