<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class RosterController extends Controller
{
    public function index()
    {
        return view('hr.rosters.index');
    }
}
