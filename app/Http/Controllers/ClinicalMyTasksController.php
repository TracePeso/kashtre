<?php

namespace App\Http\Controllers;

class ClinicalMyTasksController extends Controller
{
    public function index()
    {
        if (config('clinical_replacement.enabled')) {
            return redirect()->route('clinical.replacement.show');
        }
        return view('clinical.my-tasks.index');
    }
}
