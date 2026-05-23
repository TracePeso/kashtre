<?php

namespace App\Services;

use App\Models\HrDutyRosterEntry;
use App\Models\StaffAssignment;
use Illuminate\Support\Str;

class LocumDisciplineResolver
{
    public function assignmentLabel(StaffAssignment $assignment): string
    {
        return $this->clean($assignment->staff_title ?: $assignment->staff_cadre);
    }

    public function entryLabel(HrDutyRosterEntry $entry): string
    {
        if ($entry->relationLoaded('staffAssignment') && $entry->staffAssignment) {
            $label = $this->assignmentLabel($entry->staffAssignment);

            if ($label !== '') {
                return $label;
            }
        }

        return $this->clean($entry->staff_cadre);
    }

    public function key(?string $label): string
    {
        return Str::of((string) $label)
            ->squish()
            ->lower()
            ->toString();
    }

    public function clean(?string $label): string
    {
        return Str::of((string) $label)
            ->squish()
            ->trim()
            ->toString();
    }

    public function matchesAssignment(StaffAssignment $assignment, string $disciplineKey): bool
    {
        return $this->key($this->assignmentLabel($assignment)) === $disciplineKey;
    }
}
