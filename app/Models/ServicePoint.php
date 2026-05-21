<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePoint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'business_id',
        'branch_id',
        'room_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'business_id' => 'integer',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    protected static function booted()
    {
        static::creating(function ($servicePoint) {
            $servicePoint->uuid = (string) Str::uuid();
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function serviceQueues()
    {
        return $this->hasMany(ServiceQueue::class);
    }

    public function serviceDeliveryQueues()
    {
        return $this->hasMany(ServiceDeliveryQueue::class);
    }

    public function pendingDeliveryQueues()
    {
        return $this->serviceDeliveryQueues()
            ->whereNotNull('client_id')
            ->where('status', 'pending')
            ->orderBy('queued_at');
    }

    public function partiallyDoneDeliveryQueues()
    {
        return $this->serviceDeliveryQueues()
            ->whereNotNull('client_id')
            ->where('status', 'partially_done')
            ->orderBy('started_at');
    }

    public function pendingQueues()
    {
        return $this->serviceQueues()->pending()->orderBy('queue_number');
    }

    public function inProgressQueues()
    {
        return $this->serviceQueues()->inProgress()->orderBy('started_at');
    }

    public function completedQueuesToday()
    {
        return $this->serviceQueues()->completed()->today()->orderBy('completed_at', 'desc');
    }

    public function supervisors()
    {
        return $this->hasMany(ServicePointSupervisor::class);
    }

    public function supervisor()
    {
        return $this->hasOne(ServicePointSupervisor::class)->latestOfMany();
    }

    /**
     * Get the next queue number for this service point
     */
    public function getNextQueueNumberAttribute()
    {
        return ServiceQueue::generateQueueNumber($this->id, $this->business_id);
    }

    /**
     * Get the current queue statistics
     */
    public function getQueueStatsAttribute()
    {
        // Keep dashboard card stats aligned with service-point detail pages:
        // both should rely on ServiceDeliveryQueue client counts only.
        $deliveryQueuePending = $this->pendingDeliveryQueues()->distinct('client_id')->count('client_id');
        $deliveryQueuePartiallyDone = $this->partiallyDoneDeliveryQueues()->distinct('client_id')->count('client_id');
        $deliveryQueueInProgress = $this->serviceDeliveryQueues()->whereNotNull('client_id')->where('status', 'in_progress')->distinct('client_id')->count('client_id');
        $deliveryQueueCompletedToday = $this->serviceDeliveryQueues()->whereNotNull('client_id')->where('status', 'completed')->whereDate('completed_at', today())->distinct('client_id')->count('client_id');
        $deliveryQueueTotalToday = $this->serviceDeliveryQueues()->whereNotNull('client_id')->whereDate('queued_at', today())->distinct('client_id')->count('client_id');
        
        return [
            'pending' => $deliveryQueuePending,
            'partially_done' => $deliveryQueuePartiallyDone,
            'in_progress' => $deliveryQueueInProgress,
            'completed_today' => $deliveryQueueCompletedToday,
            'total_today' => $deliveryQueueTotalToday,
        ];
    }
}
