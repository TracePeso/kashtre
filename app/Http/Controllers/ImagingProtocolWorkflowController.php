<?php

namespace App\Http\Controllers;

use App\Models\ImagingProtocol;

class ImagingProtocolWorkflowController extends Controller
{
    public function edit(ImagingProtocol $imagingProtocol)
    {
        return view('imaging.protocols.workflow', ['protocol' => $imagingProtocol]);
    }
}
