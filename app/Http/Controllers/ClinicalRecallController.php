<?php

namespace App\Http\Controllers;

class ClinicalRecallController extends Controller
{
    public function index()
    {
        return view('clinical.recalls.index');
    }
}
