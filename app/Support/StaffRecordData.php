<?php

namespace App\Support;

use Illuminate\Support\Arr;

class StaffRecordData
{
    public static function uuid(array $staff): ?string
    {
        return self::firstString($staff, ['uuid', 'id', 'staff_uuid']);
    }

    public static function name(array $staff): ?string
    {
        $name = self::firstString($staff, ['name', 'full_name', 'staff_name']);

        if ($name) {
            return $name;
        }

        $first = self::firstString($staff, ['first_name', 'firstname', 'given_name']) ?? '';
        $last = self::firstString($staff, ['last_name', 'lastname', 'surname', 'family_name']) ?? '';
        $fullName = trim("{$first} {$last}");

        return $fullName !== '' ? $fullName : null;
    }

    public static function cadre(array $staff): ?string
    {
        return self::firstString($staff, [
            'qualification.name',
            'qualification_name',
            'qualification',
            'cadre.name',
            'cadre',
            'staff_cadre',
            'discipline.name',
            'discipline_name',
            'discipline',
        ]);
    }

    public static function department(array $staff): ?string
    {
        return self::firstString($staff, [
            'department.name',
            'department_name',
            'department',
            'dept.name',
            'dept_name',
            'dept',
            'division.name',
            'division_name',
            'division',
            'team.name',
            'team_name',
            'unit.name',
            'unit_name',
        ]);
    }

    public static function departmentExternalId(array $staff): ?string
    {
        return self::firstString($staff, [
            'department.uuid',
            'department.id',
            'department_uuid',
            'department_id',
            'dept.uuid',
            'dept.id',
            'dept_uuid',
            'dept_id',
        ]);
    }

    public static function section(array $staff): ?string
    {
        return self::firstString($staff, [
            'section.name',
            'section_name',
            'section',
            'sub_department.name',
            'sub_department_name',
            'sub_department',
            'team.name',
            'team_name',
            'team',
        ]);
    }

    public static function sectionExternalId(array $staff): ?string
    {
        return self::firstString($staff, [
            'section.uuid',
            'section.id',
            'section_uuid',
            'section_id',
            'sub_department.uuid',
            'sub_department.id',
            'sub_department_uuid',
            'sub_department_id',
            'team.uuid',
            'team.id',
            'team_uuid',
            'team_id',
        ]);
    }

    public static function title(array $staff): ?string
    {
        return self::firstString($staff, [
            'title.name',
            'title_name',
            'title',
            'job_title.name',
            'job_title',
            'position.name',
            'position_name',
            'position',
            'designation.name',
            'designation_name',
            'designation',
            'role.name',
            'role',
        ]);
    }

    public static function branchExternalId(array $staff): ?string
    {
        return self::firstString($staff, [
            'branch.uuid',
            'branch.id',
            'branch_uuid',
            'branch_id',
            'home_branch.uuid',
            'home_branch.id',
            'home_branch_uuid',
            'home_branch_id',
        ]);
    }

    public static function branchName(array $staff): ?string
    {
        return self::firstString($staff, [
            'branch.name',
            'branch_name',
            'home_branch.name',
            'home_branch_name',
        ]);
    }

    private static function firstString(array $staff, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($staff, $key);

            if (is_scalar($value)) {
                $value = trim((string) $value);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
