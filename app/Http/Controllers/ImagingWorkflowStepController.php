<?php

namespace App\Http\Controllers;

class ImagingWorkflowStepController extends Controller
{
    public function index()
    {
        return view('imaging.workflow-steps.index');
    }
}
