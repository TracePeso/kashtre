<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class OrganizationLeaveController extends Controller
{
    public function index()
    {
        return view('hr.organization-leaves.index');
    }
}
