<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class OpenShiftController extends Controller
{
    public function index()
    {
        return view('hr.open-shifts.index');
    }
}
