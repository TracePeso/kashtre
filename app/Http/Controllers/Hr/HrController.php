<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\HrModuleApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrController extends Controller
{
    public function __construct(private HrModuleApiClient $hr) {}

    /**
     * Same check-and-redirect shape already used elsewhere in this app
     * (ClientController's 'Admit Clients'/'Discharge Clients' checks) --
     * permissions are plain strings stored directly on the user's own
     * record (users.permissions, a JSON array), not a separate
     * relationship, so a straight in_array() is the real mechanism here,
     * not a Gate or a package.
     */
    private function ensurePermission(Request $request, string $permission): ?RedirectResponse
    {
        if (in_array($permission, Auth::user()->permissions ?? [], true)) {
            return null;
        }

        return redirect()->route('hr.dashboard')
            ->with('error', "You do not have permission to do that ({$permission}).");
    }

    public function employees(Request $request)
    {
        if ($denied = $this->ensurePermission($request, 'View HR Staff')) {
            return $denied;
        }

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
        if ($denied = $this->ensurePermission($request, 'View HR Attendance')) {
            return $denied;
        }

        $businessId = Auth::user()->business_id;

        try {
            $records = $this->hr->attendanceRecords($businessId, $request->only(['start_date', 'end_date', 'employee_id']));
            $summary = $this->hr->attendanceSummary($businessId, $request->input('date', \App\Support\SharedTime::businessToday((string) $businessId)));
        } catch (\Throwable $e) {
            $records = ['data' => [], 'error' => $e->getMessage()];
            $summary = [];
        }

        return view('hr.attendance.index', ['records' => $records, 'summary' => $summary]);
    }

    public function leave(Request $request)
    {
        if ($denied = $this->ensurePermission($request, 'View HR Leave')) {
            return $denied;
        }

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
        if ($denied = $this->ensurePermission($request, 'View HR Payroll')) {
            return $denied;
        }

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
        if ($denied = $this->ensurePermission($request, 'View HR Performance')) {
            return $denied;
        }

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
        if ($denied = $this->ensurePermission($request, 'View HR Reports')) {
            return $denied;
        }

        $businessId = Auth::user()->business_id;

        try {
            $attendance = $this->hr->attendanceSummary($businessId, \App\Support\SharedTime::businessToday((string) $businessId));
            $stats      = $this->hr->stats($businessId);
        } catch (\Throwable $e) {
            $attendance = [];
            $stats      = ['error' => $e->getMessage()];
        }

        return view('hr.reports.index', ['attendance' => $attendance, 'stats' => $stats]);
    }

    public function employeeRecords(Request $request)
    {
        if ($denied = $this->ensurePermission($request, 'View HR Staff')) {
            return $denied;
        }

        return redirect()->route('hr.embed', ['path' => '/hr/employee-records']);
    }

    public function recognition(Request $request)
    {
        if ($denied = $this->ensurePermission($request, 'View HR Recognition')) {
            return $denied;
        }

        return redirect()->route('hr.embed', ['path' => '/hr/recognition']);
    }

    public function settings(Request $request)
    {
        if ($denied = $this->ensurePermission($request, 'View HR Setup')) {
            return $denied;
        }

        return redirect()->route('hr.embed', ['path' => '/hr/settings']);
    }

    public function attendanceExceptions(Request $request)
    {
        if ($denied = $this->ensurePermission($request, 'View HR Attendance Exceptions')) {
            return $denied;
        }

        return redirect()->route('hr.embed', ['path' => '/hr/attendance/exceptions/pending']);
    }

    /**
     * The four wrapper methods above check permission before redirecting
     * here -- but /hr/embed?path=... is its own plain route with no path
     * restriction, directly reachable by anyone who just types the URL,
     * bypassing those wrapper checks entirely. The actual enforcement has
     * to live here, keyed off the requested path itself, not just in the
     * convenience wrappers that happen to redirect into it.
     */
    private const EMBED_PATH_PERMISSIONS = [
        '/hr/employee-records' => 'View HR Staff',
        '/hr/recognition' => 'View HR Recognition',
        '/hr/settings' => 'View HR Setup',
        '/hr/attendance/exceptions/pending' => 'View HR Attendance Exceptions',
    ];

    public function embed(Request $request)
    {
        $user    = Auth::user();
        $apiKey  = config('services.hr_module.api_key', '');
        $hrUrl   = rtrim((string) config('services.hr_module.url', ''), '/');

        // SameSite=Lax cookies only care about scheme + registrable domain,
        // not port, so a subdomain (hr.kashtre.com) or a different local
        // port (localhost:8001) are both already same-site as this app —
        // no host substitution needed, just use the configured URL as-is.
        $path = $request->input('path', '/hr/dashboard');

        foreach (self::EMBED_PATH_PERMISSIONS as $prefix => $permission) {
            if (str_starts_with($path, $prefix) && ! in_array($permission, $user->permissions ?? [], true)) {
                return redirect()->route('hr.dashboard')
                    ->with('error', "You do not have permission to do that ({$permission}).");
            }
        }

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
