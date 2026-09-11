<?php

namespace App\Livewire\Clinical;

use App\Contracts\Clinical\CareAccessGateway;
use App\Contracts\Clinical\ClinicalSettingsGateway;
use App\Models\ClinicalCareAssignment;
use App\Models\User;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Who is responsible for this patient, and changing it — SRD §2/§2.2.
 *
 * A fresh PARTICIPATION_PRIMARY assignment *is* the "change of care" /
 * handover act (it supersedes whoever holds it now); CO_MANAGING adds a
 * second clinician alongside without displacing the first. Both go through
 * the same CareAccessGateway::assign() the rest of the module's ReBAC reads
 * come from, so this panel is correct under either CLINICAL_DRIVER —
 * CO_MANAGING is refused with a clear message under `local`, which has no
 * schema for more than one responsible clinician per patient.
 */
#[Lazy]
class CareTeamPanel extends Component
{
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.clinical._lazy-placeholder');
    }

    public string $clientId;

    public ?string $visitId = null;

    public string $assignmentModel = 'INDIVIDUAL';

    public string $participation = 'PRIMARY';

    /**
     * A single merged picker over both clinical-staff pools ("Clinical Staff
     * Available") — the value carries which pool the pick came from, e.g.
     * "doctor:5" or "nurse:7", since the same select can't otherwise tell a
     * doctor's id from a nurse's id (and a user holding both permissions
     * would collide on a bare numeric value). Resolved back into
     * primary_doctor_id / primary_nurse_id at submit time in assign().
     */
    public string $selectedStaffId = '';

    public string $assignedTeamId = '';

    public string $assignedRoleCode = '';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(string $clientId, ?string $visitId = null): void
    {
        abort_unless(in_array('View Care Assignments', Auth::user()->permissions ?? []), 403);

        $this->clientId = $clientId;
        $this->visitId = $visitId;
    }

    public function render()
    {
        $actor = $this->actor();

        $doctors = $this->staffWithPermission('Act As Consultant (Clinical)');
        $nurses = $this->staffWithPermission('Act As Ward Nurse (Clinical)');

        // "Clinical Staff Available" — one merged, role-tagged list instead
        // of two separate Doctor/Nurse dropdowns. A user holding both
        // permissions appears twice, once per pool, since each row still
        // resolves to a distinct primary_doctor_id/primary_nurse_id on submit.
        $clinicalStaff = $doctors->map(fn (User $u) => (object) [
            'value' => "doctor:{$u->id}",
            'label' => "{$u->name} (Doctor)",
        ])->concat($nurses->map(fn (User $u) => (object) [
            'value' => "nurse:{$u->id}",
            'label' => "{$u->name} (Nurse)",
        ]))->sortBy('label')->values();

        return view('livewire.clinical.care-team-panel', [
            'team' => app(CareAccessGateway::class)->teamFor($actor, $this->clientId, $this->visitId),
            'clinicalStaff' => $clinicalStaff,
            'careTeams' => collect(app(ClinicalSettingsGateway::class)->list($actor, 'settings/care-teams', ['status' => 'ACTIVE'])),
        ]);
    }

    public function assign(): void
    {
        abort_unless(in_array('Manage Care Assignments', Auth::user()->permissions ?? []), 403);

        $this->validate([
            'assignmentModel' => ['required', 'in:INDIVIDUAL,ROLE,TEAM,HYBRID'],
            'participation' => ['required', 'in:PRIMARY,CO_MANAGING'],
        ]);

        if ($this->assignmentModel === 'INDIVIDUAL' && $this->selectedStaffId === '') {
            $this->errorMessage = 'Pick a clinical staff member for an individual assignment.';

            return;
        }

        if ($this->assignmentModel === 'TEAM' && $this->assignedTeamId === '') {
            $this->errorMessage = 'Pick a care team.';

            return;
        }

        if ($this->assignmentModel === 'ROLE' && $this->assignedRoleCode === '') {
            $this->errorMessage = 'Enter the role code this assignment is made to.';

            return;
        }

        // The merged picker's value is "doctor:{id}" or "nurse:{id}" — split
        // back into exactly one of the two fields the gateway still expects.
        [$staffRole, $staffId] = $this->selectedStaffId !== ''
            ? array_pad(explode(':', $this->selectedStaffId, 2), 2, null)
            : [null, null];

        $this->errorMessage = null;
        $this->statusMessage = null;

        try {
            app(CareAccessGateway::class)->assign($this->actor(), [
                'patient_id' => $this->clientId,
                'visit_id' => $this->visitId,
                'assignment_model' => $this->assignmentModel,
                'participation' => $this->participation,
                'primary_doctor_id' => $staffRole === 'doctor' ? (int) $staffId : null,
                'primary_nurse_id' => $staffRole === 'nurse' ? (int) $staffId : null,
                'assigned_team_id' => $this->assignedTeamId !== '' ? (int) $this->assignedTeamId : null,
                'assigned_role_code' => $this->assignedRoleCode ?: null,
            ]);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = $this->participation === 'CO_MANAGING'
            ? 'Added as co-managing.'
            : 'Care handed over.';

        $this->reset(['selectedStaffId', 'assignedTeamId', 'assignedRoleCode']);
    }

    public function endAssignment(int|string $assignmentId): void
    {
        abort_unless(in_array('Manage Care Assignments', Auth::user()->permissions ?? []), 403);

        $this->errorMessage = null;
        $this->statusMessage = null;

        try {
            app(CareAccessGateway::class)->endAssignment($this->actor(), $assignmentId);
        } catch (ClinicalApiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = 'Assignment ended.';
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function staffWithPermission(string $permission)
    {
        return User::where('business_id', Auth::user()->business_id)
            ->get(['id', 'name', 'permissions'])
            ->filter(fn (User $u) => in_array($permission, $u->permissions ?? [], true))
            ->values();
    }

    private function actor(): ClinicalActor
    {
        return ClinicalActor::fromUser(Auth::user());
    }
}
