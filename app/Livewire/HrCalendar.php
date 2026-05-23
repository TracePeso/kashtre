<?php

namespace App\Livewire;

use App\Models\HrCalendarEvent;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HrCalendar extends Component
{
    // Weekend config (FullCalendar day numbers: 0=Sun … 6=Sat)
    public array $weekendDays = [0, 6];

    // Holiday modal state
    public ?int $editingEventId = null;
    public bool $showEventModal = false;
    public string $title = '';
    public ?string $startsOn = null;
    public ?string $endsOn = null;
    public bool $repeatsYearly = true;
    public bool $isActive = true;
    public string $rewardType = HrCalendarEvent::REWARD_MULTIPLIER_PAY;
    public bool $blocksRosters = false;
    public string $description = '';

    public bool $canAddCalendarEvents = false;
    public bool $canEditCalendarEvents = false;

    public function mount(): void
    {
        $this->setPermissions();
        $org = Organization::current();
        $this->weekendDays = $org?->weekend_days ?? [0, 6];
    }

    public function toggleWeekendDay(int $day): void
    {
        abort_unless($this->canEditCalendarEvents, 403);

        if (in_array($day, $this->weekendDays)) {
            $this->weekendDays = array_values(array_filter($this->weekendDays, fn ($d) => $d !== $day));
        } else {
            $this->weekendDays[] = $day;
        }

        $org = Organization::current();
        if ($org) {
            $org->update(['weekend_days' => $this->weekendDays]);
        }

        $this->dispatch('weekend-days-updated', days: $this->weekendDays);
    }

    public function openCreateModal(?string $date = null): void
    {
        abort_unless($this->canAddCalendarEvents, 403);

        $this->resetValidation();
        $this->resetForm();
        $this->startsOn = $this->validDate($date) ? $date : CarbonImmutable::now()->toDateString();
        $this->showEventModal = true;
    }

    public function openEditModal(int $eventId): void
    {
        abort_unless($this->canEditCalendarEvents, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $event = HrCalendarEvent::forOrganization($org)->findOrFail($eventId);

        $this->resetValidation();
        $this->editingEventId = $event->id;
        $this->title = $event->title;
        $this->startsOn = $event->starts_on?->toDateString();
        $this->endsOn = $event->ends_on?->toDateString();
        $this->repeatsYearly = $event->repeats_yearly;
        $this->isActive = $event->is_active;
        $this->rewardType = $event->reward_type ?: HrCalendarEvent::REWARD_MULTIPLIER_PAY;
        $this->blocksRosters = $event->blocks_rosters;
        $this->description = $event->description ?? '';
        $this->showEventModal = true;
    }

    public function saveEvent(): void
    {
        $isEditing = (bool) $this->editingEventId;
        abort_unless($isEditing ? $this->canEditCalendarEvents : $this->canAddCalendarEvents, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $data = $this->validate([
            'title'         => ['required', 'string', 'max:255'],
            'startsOn'      => ['required', 'date'],
            'endsOn'        => ['nullable', 'date', 'after_or_equal:startsOn'],
            'repeatsYearly' => ['boolean'],
            'isActive'      => ['boolean'],
            'rewardType'    => ['required', 'string', 'in:leave_day,multiplier_pay,none'],
            'blocksRosters' => ['boolean'],
            'description'   => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = [
            'title'              => $data['title'],
            'event_type'         => HrCalendarEvent::TYPE_PUBLIC_HOLIDAY,
            'starts_on'          => $data['startsOn'],
            'ends_on'            => $data['endsOn'] ?: null,
            'repeats_yearly'     => (bool) $data['repeatsYearly'],
            'affects_rosters'    => true,
            'reward_type'        => $data['rewardType'],
            'blocks_rosters'     => (bool) $data['blocksRosters'],
            'is_active'          => (bool) $data['isActive'],
            'description'        => $data['description'] ?: null,
            'updated_by_user_id' => Auth::id(),
        ];

        if ($isEditing) {
            HrCalendarEvent::forOrganization($org)->findOrFail($this->editingEventId)->update($payload);
            session()->flash('message', 'Public holiday updated.');
        } else {
            $requiresApproval = ! $this->canEditCalendarEvents;
            HrCalendarEvent::create($payload + [
                'organization_id'    => $org->id,
                'created_by_user_id' => Auth::id(),
                'approval_status'    => $requiresApproval
                    ? HrCalendarEvent::APPROVAL_PENDING
                    : HrCalendarEvent::APPROVAL_APPROVED,
                'approved_by_user_id' => $requiresApproval ? null : Auth::id(),
                'approved_at'        => $requiresApproval ? null : now(),
            ]);
            session()->flash('message', $requiresApproval
                ? 'Public holiday submitted for approval.'
                : 'Public holiday added.');
        }

        $this->showEventModal = false;
        $this->resetForm();
        $this->dispatch('calendar-updated');
    }

    public function seedDefaultHolidays(): void
    {
        abort_unless($this->canEditCalendarEvents, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        $year = CarbonImmutable::now()->year;
        $easterBase = CarbonImmutable::createFromTimestamp(easter_date($year));

        $defaults = [
            // Fixed-date holidays — repeats yearly
            ['title' => "New Year's Day",              'date' => "{$year}-01-01", 'repeats' => true],
            ['title' => 'NRM Liberation Day',          'date' => "{$year}-01-26", 'repeats' => true],
            ['title' => 'Archbishop Janani Luwum Day', 'date' => "{$year}-02-16", 'repeats' => true],
            ['title' => 'International Women\'s Day',  'date' => "{$year}-03-08", 'repeats' => true],
            ['title' => 'International Workers\' Day', 'date' => "{$year}-05-01", 'repeats' => true],
            ['title' => 'Uganda Martyrs Day',          'date' => "{$year}-06-03", 'repeats' => true],
            ['title' => 'National Heroes Day',         'date' => "{$year}-06-09", 'repeats' => true],
            ['title' => 'Independence Day',            'date' => "{$year}-10-09", 'repeats' => true],
            ['title' => 'Christmas Day',               'date' => "{$year}-12-25", 'repeats' => true],
            ['title' => 'Boxing Day',                  'date' => "{$year}-12-26", 'repeats' => true],
            // Easter — moveable, current year only
            ['title' => 'Good Friday',   'date' => $easterBase->subDays(2)->toDateString(), 'repeats' => false],
            ['title' => 'Easter Sunday', 'date' => $easterBase->toDateString(),              'repeats' => false],
            ['title' => 'Easter Monday', 'date' => $easterBase->addDay()->toDateString(),    'repeats' => false],
        ];

        $existing = HrCalendarEvent::forOrganization($org)
            ->where('event_type', HrCalendarEvent::TYPE_PUBLIC_HOLIDAY)
            ->pluck('title')
            ->map(fn ($t) => strtolower($t))
            ->all();

        $added = 0;
        foreach ($defaults as $holiday) {
            if (in_array(strtolower($holiday['title']), $existing)) {
                continue;
            }

            HrCalendarEvent::create([
                'organization_id'    => $org->id,
                'title'              => $holiday['title'],
                'event_type'         => HrCalendarEvent::TYPE_PUBLIC_HOLIDAY,
                'starts_on'          => $holiday['date'],
                'ends_on'            => null,
                'repeats_yearly'     => $holiday['repeats'],
                'affects_rosters'    => true,
                'reward_type'        => HrCalendarEvent::REWARD_MULTIPLIER_PAY,
                'blocks_rosters'     => false,
                'is_active'          => true,
                'approval_status'    => HrCalendarEvent::APPROVAL_APPROVED,
                'approved_by_user_id' => Auth::id(),
                'approved_at'        => now(),
                'created_by_user_id' => Auth::id(),
                'updated_by_user_id' => Auth::id(),
            ]);

            $added++;
        }

        session()->flash('message', $added > 0
            ? "{$added} default holiday(s) added."
            : 'Default holidays are already configured.');

        $this->dispatch('calendar-updated');
    }

    public function approveEvent(int $eventId): void
    {
        abort_unless($this->canEditCalendarEvents, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        HrCalendarEvent::forOrganization($org)->findOrFail($eventId)->update([
            'approval_status' => HrCalendarEvent::APPROVAL_APPROVED,
            'approved_by_user_id' => Auth::id(),
            'approved_at' => now(),
            'updated_by_user_id' => Auth::id(),
        ]);

        session()->flash('message', 'Public holiday approved.');
        $this->dispatch('calendar-updated');
    }

    public function rejectEvent(int $eventId): void
    {
        abort_unless($this->canEditCalendarEvents, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        HrCalendarEvent::forOrganization($org)->findOrFail($eventId)->update([
            'approval_status' => HrCalendarEvent::APPROVAL_REJECTED,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'updated_by_user_id' => Auth::id(),
        ]);

        session()->flash('message', 'Public holiday rejected.');
        $this->dispatch('calendar-updated');
    }

    public function deleteEvent(int $eventId): void
    {
        abort_unless($this->canEditCalendarEvents, 403);

        $org = Organization::current();
        if (! $org) {
            return;
        }

        HrCalendarEvent::forOrganization($org)->findOrFail($eventId)->delete();
        session()->flash('message', 'Public holiday deleted.');
        $this->dispatch('calendar-updated');
    }

    public function render(): View
    {
        $this->setPermissions();

        $org = Organization::current();
        $holidays = $org
            ? HrCalendarEvent::forOrganization($org)
                ->publicHolidays()
                ->orderByRaw("CASE approval_status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
                ->orderByDesc('is_active')
                ->orderBy('starts_on')
                ->orderBy('title')
                ->get()
            : collect();

        return view('livewire.hr-calendar', [
            'holidays'         => $holidays,
            'holidayCount'     => $holidays->filter(fn (HrCalendarEvent $event): bool => $event->is_active && $event->isApproved())->count(),
            'pendingHolidayCount' => $holidays->where('approval_status', HrCalendarEvent::APPROVAL_PENDING)->count(),
            'weekendDays'      => $this->weekendDays,
            'googleApiKey'     => config('services.google.calendar_api_key', ''),
            'googleCalendarId' => config('services.google.holiday_calendar_id', ''),
            'eventsUrl'        => route('hr.calendar.events'),
        ]);
    }

    private function setPermissions(): void
    {
        $this->canAddCalendarEvents  = Auth::user()?->canAddHrSetup()  ?? false;
        $this->canEditCalendarEvents = Auth::user()?->canEditHrSetup() ?? false;
    }

    private function resetForm(): void
    {
        $this->editingEventId = null;
        $this->title          = '';
        $this->startsOn       = null;
        $this->endsOn         = null;
        $this->repeatsYearly  = true;
        $this->isActive       = true;
        $this->rewardType     = HrCalendarEvent::REWARD_MULTIPLIER_PAY;
        $this->blocksRosters  = false;
        $this->description    = '';
    }

    private function validDate(?string $date): bool
    {
        if (! $date) {
            return false;
        }

        try {
            CarbonImmutable::parse($date);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
