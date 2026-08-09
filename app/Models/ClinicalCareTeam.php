<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalCareTeam extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_care_teams';

    protected $fillable = [
        'business_id',
        'branch_id',
        'team_code',
        'team_name',
        'specialty',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'branch_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function members()
    {
        return $this->hasMany(ClinicalCareTeamMember::class, 'team_id');
    }
}
