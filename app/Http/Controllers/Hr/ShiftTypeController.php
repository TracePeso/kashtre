<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class ShiftTypeController extends Controller
{
    public function index()
    {
        return view('hr.shift-types.index');
    }
}
