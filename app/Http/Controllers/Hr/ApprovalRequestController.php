<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

class ApprovalRequestController extends Controller
{
    public function index()
    {
        return view('hr.approval-requests.index');
    }
}
