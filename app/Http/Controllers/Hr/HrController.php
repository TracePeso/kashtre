<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\HrModuleApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrController extends Controller
{
    public function __construct(private HrModuleApiClient $hr) {}

    public function employees(Request $request)
    {
        $businessId = Auth::user()->business_id;

        try {
            $data = $this->hr->employees($businessId, $request->only(['search', 'page', 'per_page']));
        } catch (\Throwable $e) {
            $data = ['data' => [], 'error' => 'Could not reach HR module: ' . $e->getMessage()];
        }

        return view('hr.employees.index', ['response' => $data]);
    }

    public function attendance(Request $request)
    {
        $businessId = Auth::user()->business_id;

        try {
            $records = $this->hr->attendanceRecords($businessId, $request->only(['start_date', 'end_date', 'employee_id']));
            $summary = $this->hr->attendanceSummary($businessId, $request->input('date', today()->toDateString()));
        } catch (\Throwable $e) {
            $records = ['data' => [], 'error' => $e->getMessage()];
            $summary = [];
        }

        return view('hr.attendance.index', ['records' => $records, 'summary' => $summary]);
    }

    public function leave(Request $request)
    {
        $businessId = Auth::user()->business_id;

        try {
            $data = $this->hr->payslips($businessId); // placeholder until leave endpoint exists
        } catch (\Throwable $e) {
            $data = ['data' => [], 'error' => $e->getMessage()];
        }

        return view('hr.leave.index', ['response' => $data]);
    }

    public function payroll(Request $request)
    {
        $businessId = Auth::user()->business_id;

        try {
            $data = $this->hr->payslips($businessId, $request->only(['month', 'year', 'page']));
        } catch (\Throwable $e) {
            $data = ['data' => [], 'error' => $e->getMessage()];
        }

        return view('hr.payroll.index', ['response' => $data]);
    }

    public function performance(Request $request)
    {
        $businessId = Auth::user()->business_id;

        try {
            $data = $this->hr->get('performance/reviews', $businessId ? ['business_id' => $businessId] : []);
        } catch (\Throwable $e) {
            $data = ['data' => [], 'error' => 'Could not reach HR module: ' . $e->getMessage()];
        }

        return view('hr.performance.index', ['response' => $data]);
    }

    public function reports(Request $request)
    {
        $businessId = Auth::user()->business_id;

        try {
            $attendance = $this->hr->attendanceSummary($businessId, today()->toDateString());
            $stats      = $this->hr->stats($businessId);
        } catch (\Throwable $e) {
            $attendance = [];
            $stats      = ['error' => $e->getMessage()];
        }

        return view('hr.reports.index', ['attendance' => $attendance, 'stats' => $stats]);
    }

    public function employeeRecords(Request $request)
    {
        return redirect()->route('hr.embed', ['path' => '/hr/employee-records']);
    }

    public function recognition(Request $request)
    {
        return redirect()->route('hr.embed', ['path' => '/hr/recognition']);
    }

    public function settings(Request $request)
    {
        return redirect()->route('hr.embed', ['path' => '/hr/settings']);
    }

    public function embed(Request $request)
    {
        $user    = Auth::user();
        $apiKey  = config('services.hr_module.api_key', '');
        $hrUrl   = rtrim(config('services.hr_module.url', ''), '/');

        // Mirror the host the user is on so the iframe is same-site:
        // avoids SameSite=Lax cookie blocking for cross-host iframes.
        $parsed  = parse_url($hrUrl);
        $hrPort  = $parsed['port'] ?? 8001;
        $hrUrl   = ($parsed['scheme'] ?? 'http') . '://' . $request->getHost() . ':' . $hrPort;

        $path    = $request->input('path', '/hr/dashboard');
        $payload = $user->email . '|' . ($user->business_id ?? '') . '|' . time();
        $sig     = hash_hmac('sha256', $payload, $apiKey);
        $token   = base64_encode($payload . ':' . $sig);

        $iframeSrc = $hrUrl . '/hr/sso'
            . '?token='    . urlencode($token)
            . '&redirect=' . urlencode($path)
            . '&embedded=1';

        return view('hr.embed', ['iframeSrc' => $iframeSrc, 'path' => $path]);
    }

    public function stats()
    {
        $businessId = Auth::user()->business_id;

        try {
            $data = $this->hr->stats($businessId);
        } catch (\Throwable $e) {
            $data = ['error' => $e->getMessage()];
        }

        return view('hr.dashboard', ['stats' => $data]);
    }
}
