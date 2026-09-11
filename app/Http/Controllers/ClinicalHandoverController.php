<?php

namespace App\Http\Controllers;

class ClinicalHandoverController extends Controller
{
    public function index()
    {
        return view('clinical.handover.index');
    }
}
