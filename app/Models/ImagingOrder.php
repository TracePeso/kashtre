<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ImagingOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'business_id',
        'branch_id',
        'client_id',
        'visit_id',
        'ordering_user_id',
        'protocol_code',
        'clinical_indication',
        'status',
        'priority',
        'imaging_study_id',
    ];

    const STATUS_PENDING = 'PENDING';
    const STATUS_ACCEPTED = 'ACCEPTED';
    const STATUS_CANCELLED = 'CANCELLED';

    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected static function booted()
    {
        static::creating(function (ImagingOrder $order) {
            $order->uuid = $order->uuid ?: (string) Str::uuid();

            if (empty($order->status)) {
                $order->status = self::STATUS_PENDING;
            }

            if (empty($order->priority)) {
                $order->priority = self::PRIORITY_NORMAL;
            }
        });

        // By the time an order reaches Imaging it's already been cleared
        // upstream — there's no separate manual "Accept" gate anymore. The
        // order converts into its study immediately; the only real handling
        // left is the radiologist/technologist claiming that study off the
        // My Queue page (Chunk 4).
        static::created(function (ImagingOrder $order) {
            $order->acceptIntoStudy($order->resolveDefaultRoom());
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

    public function imagingStudy()
    {
        return $this->belongsTo(ImagingStudy::class);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Resolve the protocol this order references.
     */
    public function protocol(): ?ImagingProtocol
    {
        return ImagingProtocol::where('code', $this->protocol_code)->first();
    }

    /**
     * Client is referenced by its permanent business-scoped code (Client::client_id),
     * not a FK, so this is a lookup rather than a real Eloquent relation.
     */
    public function resolveClient(): ?Client
    {
        return Client::where('business_id', $this->business_id)
            ->where('client_id', $this->client_id)
            ->first();
    }

    /**
     * The room to auto-route this order's study to, when its business has
     * exactly one configured imaging room — null if zero or several exist,
     * since neither case has a safe default to pick (the study just stays
     * unrouted until someone sets a room, same graceful null-handling
     * ImagingStudy::resolveHardwareAeTitle() already tolerates).
     */
    public function resolveDefaultRoom(): ?int
    {
        $configs = ImagingServicePointConfig::where('business_id', $this->business_id)->pluck('main_room_id');

        return $configs->count() === 1 ? (int) $configs->first() : null;
    }

    /**
     * Create the ImagingStudy this order describes. Called automatically
     * right after the order itself is created (see booted() above) — no
     * manual "Accept" step exists anymore. This is still the hand-off
     * point a real Clinical Module would eventually call through an API
     * instead of this in-process method.
     */
    public function acceptIntoStudy(?int $mainRoomId = null): ImagingStudy
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \RuntimeException("Imaging order {$this->id} is not pending and cannot be accepted.");
        }

        $protocol = $this->protocol();

        if (! $protocol) {
            throw new \RuntimeException("No imaging protocol found for code [{$this->protocol_code}].");
        }

        $study = ImagingStudy::create([
            'business_id' => $this->business_id,
            'branch_id' => $this->branch_id,
            'client_id' => $this->client_id,
            'visit_id' => $this->visit_id,
            'main_room_id' => $mainRoomId,
            'imaging_order_id' => $this->id,
            'protocol_code' => $protocol->code,
            'modality_type' => $protocol->modality_type,
            'priority' => $this->priority,
            'preparation_required' => ! empty($protocol->preparation_requirements),
            'consent_verified' => ! $protocol->requires_consent,
        ]);

        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'imaging_study_id' => $study->id,
        ]);

        // RIS Amendment v2.6, Chunk 3: positions the study at step 1 of its
        // protocol's active workflow. Swallowed (not fatal to order
        // acceptance) if the protocol has no workflow configured yet —
        // WorkflowEngineService::resolveOrStartExecution() lazily creates
        // one on first mark*() call anyway, matching this study's status
        // at that point, so nothing is lost by not blocking here.
        try {
            app(\App\Services\Imaging\WorkflowEngineService::class)->startWorkflow($study);
        } catch (\RuntimeException $e) {
            //
        }

        return $study;
    }
}
