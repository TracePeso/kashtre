<?php

namespace App\Http\Controllers;

use App\Services\Imaging\ImagingAnalyticsService;
use Illuminate\Support\Facades\Auth;

class ImagingAnalyticsController extends Controller
{
    public function __construct(private readonly ImagingAnalyticsService $analytics) {}

    public function index()
    {
        abort_unless(in_array('View Imaging Analytics', Auth::user()->permissions ?? []), 403);

        $businessId = Auth::user()->business_id === 1 ? null : Auth::user()->business_id;

        return view('imaging.analytics.index', [
            'studiesPerModality' => $this->analytics->studiesPerModality($businessId),
            'procedureVolumes' => $this->analytics->procedureVolumes($businessId),
            'criticalFindings' => $this->analytics->criticalFindings($businessId),
            'radiologistProductivity' => $this->analytics->radiologistProductivity($businessId),
            'avgTurnaroundHours' => $this->analytics->averageReportTurnaroundHours($businessId),
            'avgVerificationDelayHours' => $this->analytics->averageVerificationDelayHours($businessId),
        ]);
    }
}
