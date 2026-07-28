<?php

namespace App\Http\Controllers;

class ImagingReadinessCheckTypeController extends Controller
{
    public function index()
    {
        return view('imaging.readiness-check-types.index');
    }
}
