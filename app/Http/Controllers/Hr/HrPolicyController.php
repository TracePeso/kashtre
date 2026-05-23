<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class HrPolicyController extends Controller
{
    public function index()
    {
        return view('hr.policies.index');
    }
}
