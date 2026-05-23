<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HrDutyRoster extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const APPROVAL_NOT_SUBMITTED = 'not_submitted';
    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    public const AI_GENERATION_QUEUED = 'queued';
    public const AI_GENERATION_RUNNING = 'running';
    public const AI_GENERATION_COMPLETED = 'completed';
    public const AI_GENERATION_FAILED = 'failed';
    public const AI_GENERATION_SOURCE_GEMINI = 'gemini';
    public const AI_GENERATION_SOURCE_AUTO = 'auto';

    protected $fillable = [
        'uuid',
        'organization_id',
        'organizational_unit_id',
        'cadre_or_discipline',
        'discipline_titles',
        'team_definitions',
        'team_assignments',
        'name',
        'start_date',
        'end_date',
        'created_by',
        'status',
        'approval_request_id',
        'approval_status',
        'submitted_at',
        'published_at',
        'rejected_at',
        'ai_generation_status',
        'ai_generation_source',
        'ai_generation_token',
        'ai_generation_message',
        'ai_generation_attempts',
        'ai_generation_started_at',
        'ai_generation_heartbeat_at',
        'ai_generation_completed_at',
        'ai_generation_failed_at',
    ];

    protected $casts = [
        'discipline_titles' => 'array',
        'team_definitions' => 'array',
        'team_assignments' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'submitted_at' => 'datetime',
        'published_at' => 'datetime',
        'rejected_at' => 'datetime',
        'ai_generation_attempts' => 'integer',
        'ai_generation_started_at' => 'datetime',
        'ai_generation_heartbeat_at' => 'datetime',
        'ai_generation_completed_at' => 'datetime',
        'ai_generation_failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizationalUnit()
    {
        return $this->belongsTo(HrOrganizationalUnit::class, 'organizational_unit_id');
    }

    public function entries()
    {
        return $this->hasMany(HrDutyRosterEntry::class, 'duty_roster_id')
            ->orderBy('roster_date')
            ->orderBy('staff_name');
    }

    public function approvalRequest()
    {
        return $this->belongsTo(HrApprovalRequest::class, 'approval_request_id');
    }

    public function approvalRequests()
    {
        return $this->hasMany(HrApprovalRequest::class, 'linked_roster_id')->latest('id');
    }

    public function openShifts()
    {
        return $this->hasMany(HrOpenShift::class, 'duty_roster_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT
            && $this->approval_status !== self::APPROVAL_PENDING
            && ! $this->hasActiveAiGeneration();
    }

    public function hasActiveAiGeneration(): bool
    {
        return in_array($this->ai_generation_status, [
            self::AI_GENERATION_QUEUED,
            self::AI_GENERATION_RUNNING,
        ], true);
    }

    public function hasCompletedAiGeneration(): bool
    {
        return $this->ai_generation_status === self::AI_GENERATION_COMPLETED;
    }

    public function activeGenerationSource(): ?string
    {
        if (! $this->ai_generation_status) {
            return null;
        }

        return filled($this->ai_generation_source)
            ? (string) $this->ai_generation_source
            : self::AI_GENERATION_SOURCE_GEMINI;
    }

    public function hasActiveGeminiGeneration(): bool
    {
        return $this->hasActiveAiGeneration()
            && $this->activeGenerationSource() === self::AI_GENERATION_SOURCE_GEMINI;
    }

    public function hasActiveAutoGeneration(): bool
    {
        return $this->hasActiveAiGeneration()
            && $this->activeGenerationSource() === self::AI_GENERATION_SOURCE_AUTO;
    }

    /**
     * @return array<int, string>
     */
    public function disciplineTitles(): array
    {
        $titles = is_array($this->discipline_titles) && $this->discipline_titles !== []
            ? $this->discipline_titles
            : [$this->cadre_or_discipline];

        return collect($titles)
            ->map(fn ($title): string => Str::of((string) $title)->squish()->trim()->toString())
            ->filter(fn (string $title): bool => $title !== '')
            ->unique(fn (string $title): string => Str::lower($title))
            ->values()
            ->all();
    }

    public function hasMultipleDisciplines(): bool
    {
        return count($this->disciplineTitles()) > 1;
    }

    /**
     * @return array<int, string>
     */
    public function teamNames(): array
    {
        return collect(is_array($this->team_definitions) ? $this->team_definitions : [])
            ->map(fn ($team): string => Str::of((string) $team)->squish()->trim()->toString())
            ->filter(fn (string $team): bool => $team !== '')
            ->unique(fn (string $team): string => Str::lower($team))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function teamAssignments(): array
    {
        $validTeams = collect($this->teamNames())->flip();

        return collect(is_array($this->team_assignments) ? $this->team_assignments : [])
            ->mapWithKeys(function ($team, $staffAssignmentId) use ($validTeams): array {
                $staffKey = trim((string) $staffAssignmentId);
                $teamLabel = Str::of((string) $team)->squish()->trim()->toString();

                if ($staffKey === '' || $teamLabel === '' || ! $validTeams->has($teamLabel)) {
                    return [];
                }

                return [$staffKey => $teamLabel];
            })
            ->all();
    }

    public function usesTeams(): bool
    {
        return $this->teamNames() !== [];
    }

    public function teamLabelForStaffAssignment(int|string|null $staffAssignmentId): ?string
    {
        if ($staffAssignmentId === null) {
            return null;
        }

        return $this->teamAssignments()[(string) $staffAssignmentId] ?? null;
    }
}
