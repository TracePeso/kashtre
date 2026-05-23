<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class StaffAssignmentController extends Controller
{
    public function index()
    {
        return view('hr.staff-assignments.index');
    }
}
