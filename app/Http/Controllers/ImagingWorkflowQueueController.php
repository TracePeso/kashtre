<?php

namespace App\Http\Controllers;

class ImagingWorkflowQueueController extends Controller
{
    public function index()
    {
        return view('imaging.my-queue.index');
    }
}
