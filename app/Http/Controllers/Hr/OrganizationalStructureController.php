<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class OrganizationalStructureController extends Controller
{
    public function index()
    {
        return view('hr.organizational-structure.index');
    }
}
