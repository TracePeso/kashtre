<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class LeaveTypeController extends Controller
{
    public function index()
    {
        return view('hr.leave-types.index');
    }
}
