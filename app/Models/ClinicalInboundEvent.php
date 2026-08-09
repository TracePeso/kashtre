<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An event received from the Clinical Module (API Integration Guide §12).
 *
 * The row exists to answer one question — "have we already handled this
 * event_id?" — and to leave an audit trail of what arrived, including
 * deliveries we failed to process.
 */
class ClinicalInboundEvent extends Model
{
    public const STATUS_RECEIVED = 'RECEIVED';

    public const STATUS_PROCESSED = 'PROCESSED';

    public const STATUS_FAILED = 'FAILED';

    /** Emitted on a live birth; Main is expected to register the infant. */
    public const TOKEN_INFANT_REGISTRATION_REQUESTED = 'INFANT_REGISTRATION_REQUESTED';

    protected $fillable = [
        'event_id',
        'fact_token',
        'tenant_id',
        'business_id',
        'global_client_id',
        'visit_id',
        'payload',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'business_id' => 'integer',
        'processed_at' => 'datetime',
    ];
}
