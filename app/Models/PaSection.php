<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaSection extends Model
{
    protected $fillable = ['name', 'business_id'];

    public function callers()
    {
        return $this->belongsToMany(Caller::class, 'pa_section_callers', 'pa_section_id', 'caller_id');
    }
}
