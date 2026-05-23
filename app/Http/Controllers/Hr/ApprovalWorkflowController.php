<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class ApprovalWorkflowController extends Controller
{
    public function index()
    {
        return view('hr.approval-workflows.index');
    }
}
