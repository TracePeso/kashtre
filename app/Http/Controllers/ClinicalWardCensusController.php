<?php

namespace App\Http\Controllers;

class ClinicalWardCensusController extends Controller
{
    public function index()
    {
        return view('clinical.ward-census.index');
    }
}
