<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class LeaveApplicationController extends Controller
{
    public function index()
    {
        return view('hr.leave-applications.index');
    }
}
