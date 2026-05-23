<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class ClientSpaceController extends Controller
{
    public function index()
    {
        return view('hr.client-spaces.index');
    }
}
