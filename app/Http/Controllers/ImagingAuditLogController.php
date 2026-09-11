<?php

namespace App\Http\Controllers;

class ImagingAuditLogController extends Controller
{
    public function index()
    {
        return view('imaging.audit-log.index');
    }
}
