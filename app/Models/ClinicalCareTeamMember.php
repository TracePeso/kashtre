<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalCareTeamMember extends Model
{
    protected $connection = 'clinical';

    protected $table = 'clinical_care_team_members';

    protected $fillable = [
        'team_id',
        'user_id',
        'role_code',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function team()
    {
        return $this->belongsTo(ClinicalCareTeam::class, 'team_id');
    }
}
