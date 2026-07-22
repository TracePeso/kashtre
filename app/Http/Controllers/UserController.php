<?php

namespace App\Http\Controllers;

use App\Exports\StaffTemplateExport;
use App\Imports\StaffTemplateImport;
use App\Models\Business;
use App\Models\User;
use App\Traits\AccessTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    use AccessTrait;

    /**
     * Normalize empty strings to null so nullable rules and FK checks behave consistently.
     */
    protected function normalizeNullableUserEnrollment(Request $request): void
    {
        foreach ([
            'branch_id', 'qualification_id', 'department_id', 'title_id', 'staff_category_id',
            'phone', 'nin', 'gender', 'birth_date', 'marital_status',
            'surname', 'first_name', 'middle_name', 'status',
        ] as $key) {
            if ($request->input($key) === '') {
                $request->merge([$key => null]);
            }
        }
    }

    /**
     * Display name from optional HR name parts; falls back to email local-part then "User".
     */
    protected function resolveUserDisplayName(array $validated): string
    {
        $parts = array_filter(array_map('trim', [
            (string) ($validated['surname'] ?? ''),
            (string) ($validated['first_name'] ?? ''),
            (string) ($validated['middle_name'] ?? ''),
        ]));
        $fromParts = trim(implode(' ', $parts));
        if ($fromParts !== '') {
            return $fromParts;
        }
        $email = (string) ($validated['email'] ?? '');
        if ($email !== '') {
            $local = Str::before($email, '@');

            return $local !== '' ? $local : 'User';
        }

        return 'User';
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        //fecth all users
        $users = User::all();
        // Pass businesses to populate select dropdown (optional: only if admin)
        $businesses = Business::all();

        return view('users.index', compact('users', 'businesses'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $businesses = Business::all();
        // $permissions = $this->getAllPermissions();
        $app_permissions = $this->getUserPermissionOptions();

        // dd($permissions);

        return view('users.create', compact('businesses', 'app_permissions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->normalizeNullableUserEnrollment($request);

        // Determine if business_id should be required based on current user's business
        $isSuperBusiness = Auth::user()->business_id == 1;
        $businessIdRule = $isSuperBusiness ? 'required|exists:businesses,id' : 'nullable|exists:businesses,id';

        $validated = $request->validate([
            'surname' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'status' => 'nullable|in:active,inactive,suspended',
            'business_id' => $businessIdRule,
            'branch_id' => 'nullable|exists:branches,id',
            'profile_photo_path' => 'nullable|image|max:2048',
            'phone' => 'nullable|string|max:255',
            'nin' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|date',
            'marital_status' => 'nullable|in:single,married,divorced,widowed,separated,other',
            'qualification_id' => 'nullable|exists:qualifications,id',
            'department_id' => 'nullable|exists:departments,id',
            'title_id' => 'nullable|exists:titles,id',
            'staff_category_id' => 'nullable|exists:staff_categories,id',
            'service_points' => 'nullable|array',
            'service_points.*' => 'exists:service_points,id',
            'allowed_branches' => 'nullable|array',
            'permissions_menu' => 'required|array|min:1',
            // Contractor profile fields (conditionally required)
            'bank_name' => 'required_if:permissions_menu.*,Contractor|string|nullable',
            'account_name' => 'required_if:permissions_menu.*,Contractor|string|nullable',
            'account_number' => 'required_if:permissions_menu.*,Contractor|string|nullable',
        ]);

        try {
            $validated['name'] = $this->resolveUserDisplayName($validated);
            $validated['status'] = $validated['status'] ?? 'active';

            // Upload profile photo if provided
            if ($request->hasFile('profile_photo_path')) {
                $path = $request->file('profile_photo_path')->store('profile_photos', 'public');
                $validated['profile_photo_path'] = $path;
            }

            // For non-super business users, use their business_id if business_id is not provided
            if (! $isSuperBusiness && empty($validated['business_id'])) {
                $validated['business_id'] = Auth::user()->business_id;
            }

            if (empty($validated['branch_id']) && ! $isSuperBusiness) {
                $validated['branch_id'] = Auth::user()->branch_id;
            }

            // Check if user is a cashier (has Cashier permission)
            $isCashier = in_array('Cashier', $validated['permissions_menu']);

            // Create the user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'status' => $validated['status'],
                'business_id' => $validated['business_id'],
                'branch_id' => $validated['branch_id'],
                'profile_photo_path' => $validated['profile_photo_path'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'nin' => $validated['nin'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'marital_status' => $validated['marital_status'] ?? null,
                'qualification_id' => $validated['qualification_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'title_id' => $validated['title_id'] ?? null,
                'staff_category_id' => $validated['staff_category_id'] ?? null,
                'service_points' => $validated['service_points'] ?? [],
                'allowed_branches' => $validated['allowed_branches'] ?? [],
                'permissions' => $validated['permissions_menu'],
                'password' => '',
                // Keep balances non-null for all users (DB constraint on some environments).
                'total_balance' => 0.00,
                'current_balance' => 0.00,
            ]);
            // Send password setup link (uses Laravel’s password reset logic)
            Password::sendResetLink(['email' => $user->email]);

            // If Contractor permission is selected, create ContractorProfile
            if (in_array('Contractor', $validated['permissions_menu'])) {
                // Check if contractor profile already exists for this user
                $existingProfile = \App\Models\ContractorProfile::where('user_id', $user->id)->first();
                if (! $existingProfile) {
                    \App\Models\ContractorProfile::create([
                        'business_id' => $validated['business_id'],
                        'bank_name' => $validated['bank_name'],
                        'account_name' => $validated['account_name'],
                        'account_number' => $validated['account_number'],
                        'user_id' => $user->id,
                    ]);
                }
            }

            return redirect()->route('users.index')->with('success', 'User created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create user: '.$e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = User::with('business', 'branch')->findOrFail($id);
        $contractorProfile = null;
        if (in_array('Contractor', (array) $user->permissions)) {
            $contractorProfile = \App\Models\ContractorProfile::where('user_id', $user->id)
                ->where('business_id', $user->business_id)
                ->first();
        }
        $businesses = Business::all();
        // $permissions = $this->getAllPermissions();
        $app_permissions = $this->getUserPermissionOptions();
        return view('users.show', compact('user', 'contractorProfile', 'businesses', 'app_permissions'));
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $businesses = \App\Models\Business::with('branches')->get()->keyBy('id');
        $qualifications = \App\Models\Qualification::all();
        $departments = \App\Models\Department::all();
        $sections = \App\Models\Section::all();
        $titles = \App\Models\Title::all();
        $servicePoints = \App\Models\ServicePoint::all();
        $contractorProfile = null;
        if (in_array('Contractor', (array) $user->permissions)) {
            // Get the single contractor profile for this user (active or soft deleted)
            $contractorProfile = \App\Models\ContractorProfile::withTrashed()
                ->where('user_id', $user->id)
                ->first();
        }
        // Split name for form
        $nameParts = explode(' ', $user->name, 3);
        $surname = $nameParts[0] ?? '';
        $first_name = $nameParts[1] ?? '';
        $middle_name = $nameParts[2] ?? '';

        $app_permissions = $this->getUserPermissionOptions();
        return view('users.edit', compact('user', 'businesses', 'qualifications', 'departments', 'sections', 'titles', 'servicePoints', 'contractorProfile', 'surname', 'first_name', 'middle_name', 'app_permissions'));
    }

    /**
     * Staff/user forms intentionally hide most Masters permissions.
     * We add back selected Masters permissions so admins can grant them without
     * exposing the rest of the Masters block in the user editor.
     */
    private function getUserPermissionOptions(): array
    {
        $permissions = $this->getAccessControl(['Masters']);
        $permissions['Masters'] = [
            'Maturation Periods' => self::$masters['Maturation Periods'],
        ];

        return $permissions;
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $this->normalizeNullableUserEnrollment($request);

        // Determine if business_id should be required based on current user's business
        $isSuperBusiness = Auth::user()->business_id == 1;
        $businessIdRule = $isSuperBusiness ? 'required|exists:businesses,id' : 'nullable|exists:businesses,id';

        $validated = $request->validate([
            'surname' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'status' => 'nullable|in:active,inactive,suspended',
            'business_id' => $businessIdRule,
            'branch_id' => 'nullable|exists:branches,id',
            'profile_photo_path' => 'nullable|image|max:2048',
            'phone' => 'nullable|string|max:255',
            'nin' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|date',
            'marital_status' => 'nullable|in:single,married,divorced,widowed,separated,other',
            'qualification_id' => 'nullable|exists:qualifications,id',
            'department_id' => 'nullable|exists:departments,id',
            'title_id' => 'nullable|exists:titles,id',
            'staff_category_id' => 'nullable|exists:staff_categories,id',
            'service_points' => 'nullable|array',
            'service_points.*' => 'exists:service_points,id',
            'allowed_branches' => 'nullable|array',
            'allowed_branches.*' => 'exists:branches,id',
            'permissions_menu' => 'required|array|min:1',
            // Contractor profile fields (conditionally required)
            'bank_name' => 'required_if:permissions_menu.*,Contractor|string|nullable',
            'account_name' => 'required_if:permissions_menu.*,Contractor|string|nullable',
            'account_number' => 'required_if:permissions_menu.*,Contractor|string|nullable',
        ]);
        try {
            $validated['name'] = $this->resolveUserDisplayName($validated);
            $validated['status'] = $validated['status'] ?? $user->status ?? 'active';
            if ($request->hasFile('profile_photo_path')) {
                $path = $request->file('profile_photo_path')->store('profile_photos', 'public');
                $validated['profile_photo_path'] = $path;
            }

            // For non-super business users, use their business_id if business_id is not provided
            if (! $isSuperBusiness && empty($validated['business_id'])) {
                $validated['business_id'] = Auth::user()->business_id;
            }

            if (empty($validated['branch_id']) && ! $isSuperBusiness) {
                $validated['branch_id'] = Auth::user()->branch_id;
            }

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'status' => $validated['status'],
                'business_id' => $validated['business_id'],
                'branch_id' => $validated['branch_id'] ?? $user->branch_id,
                'profile_photo_path' => $validated['profile_photo_path'] ?? $user->profile_photo_path,
                'phone' => $validated['phone'] ?? null,
                'nin' => $validated['nin'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'marital_status' => $validated['marital_status'] ?? null,
                'qualification_id' => $validated['qualification_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'title_id' => $validated['title_id'] ?? null,
                'staff_category_id' => $validated['staff_category_id'] ?? null,
                'service_points' => $validated['service_points'] ?? [],
                'allowed_branches' => $validated['allowed_branches'] ?? [],
                'permissions' => $validated['permissions_menu'],
            ]);
            // Contractor profile logic
            if (in_array('Contractor', $validated['permissions_menu'])) {
                // Update or create contractor profile
                \App\Models\ContractorProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'business_id' => $validated['business_id'],
                        'bank_name' => $validated['bank_name'],
                        'account_name' => $validated['account_name'],
                        'account_number' => $validated['account_number'],
                    ]
                );
            } else {
                // Do not delete contractor profile when user doesn't have contractor permissions
                // Contractor profiles should remain active even if user permissions change
                // This prevents breaking item-contractor relationships
            }

            return redirect()->route('users.index')->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update user: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Download staff template
     */
    public function downloadTemplate(Request $request)
    {
        try {
            $businessId = $request->get('business_id');
            $branchId = $request->get('branch_id');

            // Validate business and branch
            if (! $businessId || ! $branchId) {
                return redirect()->back()->with('error', 'Business and Branch are required.');
            }

            // Check if user has permission to access this business
            if (Auth::user()->business_id !== 1 && Auth::user()->business_id != $businessId) {
                return redirect()->back()->with('error', 'You can only access your own business.');
            }

            $business = \App\Models\Business::find($businessId);
            $branch = \App\Models\Branch::find($branchId);

            $businessName = str_replace(' ', '_', $business->name);
            $branchName = str_replace(' ', '_', $branch->name);

            $filename = 'staff_template_'.$businessName.'_'.$branchName.'.xlsx';

            return Excel::download(
                new StaffTemplateExport($businessId, $branchId),
                $filename
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while generating the template: '.$e->getMessage());
        }
    }

    /**
     * Handle bulk upload of staff data
     */
    public function bulkUpload(Request $request)
    {
        $validated = $request->validate([
            'template' => 'required|file|mimes:xlsx,xls',
            'business_id' => 'required|exists:businesses,id',
            'branch_id' => 'required|exists:branches,id',
        ]);

        try {
            // Check if user has permission to access this business
            if (Auth::user()->business_id !== 1 && Auth::user()->business_id != $validated['business_id']) {
                return redirect()->back()->with('error', 'You can only upload staff for your own business.');
            }

            // Import the data
            Excel::import(new StaffTemplateImport($validated['business_id'], $validated['branch_id']), $request->file('template'));

            // Send password reset emails to newly created users (excluding business ID 1)
            $newUsers = User::where('password', '')->where('business_id', $validated['business_id'])->get();
            foreach ($newUsers as $user) {
                Password::sendResetLink(['email' => $user->email]);
            }

            return redirect()->route('users.index')->with('success', 'Staff data uploaded and processed successfully! Password reset emails have been sent to new users.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred during import: '.$e->getMessage());
        }
    }
}
