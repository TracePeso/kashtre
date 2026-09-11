<?php

namespace App\Http\Controllers;

class ImagingModalityController extends Controller
{
    public function index()
    {
        return view('imaging.modalities.index');
    }
}
