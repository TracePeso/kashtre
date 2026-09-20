<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\SharedTime;
use Carbon\Carbon;

class ServiceQueue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'client_id',
        'service_point_id',
        'user_id',
        'business_id',
        'branch_id',
        'queue_number',
        'status',
        'priority',
        'estimated_duration',
        'actual_duration',
        'notes',
        'started_at',
        'completed_at',
        'items',
        'total_amount',
        'payment_status',
    ];

    protected $casts = [
        'uuid' => 'string',
        'items' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_duration' => 'integer',
        'actual_duration' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Payment status constants
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_PARTIAL = 'partial';

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function servicePoint()
    {
        return $this->belongsTo(ServicePoint::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Generate a unique queue number for a service point
     */
    public static function generateQueueNumber($servicePointId, $businessId)
    {
        $lastQueue = self::where('service_point_id', $servicePointId)
            ->where('business_id', $businessId)
            ->whereOperationalDay('created_at', SharedTime::businessToday((string) $businessId))
            ->orderBy('queue_number', 'desc')
            ->first();

        if ($lastQueue) {
            return $lastQueue->queue_number + 1;
        }

        return 1;
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_IN_PROGRESS => 'bg-blue-100 text-blue-800',
            self::STATUS_COMPLETED => 'bg-green-100 text-green-800',
            self::STATUS_CANCELLED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get priority badge class
     */
    public function getPriorityBadgeClassAttribute()
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'bg-gray-100 text-gray-800',
            self::PRIORITY_NORMAL => 'bg-blue-100 text-blue-800',
            self::PRIORITY_HIGH => 'bg-orange-100 text-orange-800',
            self::PRIORITY_URGENT => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get payment status badge class
     */
    public function getPaymentStatusBadgeClassAttribute()
    {
        return match($this->payment_status) {
            self::PAYMENT_PENDING => 'bg-yellow-100 text-yellow-800',
            self::PAYMENT_PAID => 'bg-green-100 text-green-800',
            self::PAYMENT_PARTIAL => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get waiting time in minutes
     */
    public function getWaitingTimeAttribute()
    {
        if (!$this->started_at) {
            return Carbon::now()->diffInMinutes($this->created_at);
        }
        return $this->started_at->diffInMinutes($this->created_at);
    }

    /**
     * Get service duration in minutes
     */
    public function getServiceDurationAttribute()
    {
        if (!$this->started_at) {
            return 0;
        }
        
        $endTime = $this->completed_at ?? Carbon::now();
        return $endTime->diffInMinutes($this->started_at);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeToday($query)
    {
        return SharedTime::constrainToOperationalDay($query, 'created_at');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    protected static function booted()
    {
        static::creating(function ($serviceQueue) {
            $serviceQueue->uuid = (string) Str::uuid();
        });
    }
}

