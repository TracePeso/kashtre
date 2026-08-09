<?php

namespace App\Http\Controllers;

class ClinicalMyTasksController extends Controller
{
    public function index()
    {
        return view('clinical.my-tasks.index');
    }
}
