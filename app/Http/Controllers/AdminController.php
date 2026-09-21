<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use App\Traits\AccessTrait;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AdminTemplateExport;
use App\Imports\AdminTemplateImport;

class AdminController extends Controller
{
    use AccessTrait;

    public function index()
    {

        return view('admins.index');
    }

    public function create()
    {
        $app_permissions = $this->getAccessControl(['Stock']);

        //  dd($permissions);
         return view('admins.create', compact('app_permissions'));
        //return view('admins.test', compact('app_permissions'));
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:255',
            'nin' => 'required|string|max:255',
            'gender' => 'required|in:male,female,other',
            'permissions_menu' => 'required|array',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        try {
            $validated['name'] = trim($validated['surname'] . ' ' . $validated['first_name'] . ' ' . ($validated['middle_name'] ?? ''));

            $kashtre = Business::find(1);
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'nin' => $validated['nin'],
                'gender' => $validated['gender'],
                'business_id' => 1,
                'branch_id' => $kashtre?->branches()->first()?->id,
                'status' => $validated['status'],
                'allowed_branches' => [1],
                'permissions' => $validated['permissions_menu'],
                'password' => $kashtre?->importedUserPasswordHash() ?? '',
                'service_points' => [],
            ]);

            if ($kashtre?->sendsPasswordResetLink()) {
                Password::sendResetLink(['email' => $user->email]);
            }

            return redirect()->route('admins.index')->with('success', 'Admin created successfully.');
        } catch (\Exception $e) {
            dd($e);
            return redirect()->back()->with('error', 'Failed to create admin: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        // dd('I am here');
        $admin = User::findOrFail($id);
        $app_permissions = $this->getAccessControl();
        return view('admins.show', compact('admin', 'app_permissions'));
    }

    public function edit($id)
    {
        $admin = User::findOrFail($id);
        $nameParts = explode(' ', $admin->name, 3);
        $surname = $nameParts[0] ?? '';
        $first_name = $nameParts[1] ?? '';
        $middle_name = $nameParts[2] ?? '';
        $app_permissions = $this->getAccessControl(['Stock']);
        return view('admins.edit', compact('admin', 'surname', 'first_name', 'middle_name', 'app_permissions'));
    }

    public function update(Request $request, $id)
    {
        $admin = User::findOrFail($id);

        // dd($request->all());

        $validated = $request->validate([
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,' . $admin->id,
            'phone' => 'required|string|max:255',
            'nin' => 'required|string|max:255',
            'gender' => 'required|in:male,female,other',
            'permissions_menu' => 'required|array',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        try {
            $validated['name'] = trim($validated['surname'] . ' ' . $validated['first_name'] . ' ' . ($validated['middle_name'] ?? ''));

            $admin->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'nin' => $validated['nin'],
                'gender' => $validated['gender'],
                'permissions' => $validated['permissions_menu'],
                'status' => $validated['status'],
            ]);

            return redirect()->route('admins.index')->with('success', 'Admin updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update admin: ' . $e->getMessage());
        }
    }

    /**
     * Download admin template
     */
    public function downloadTemplate()
    {
        try {
            $filename = 'admin_template_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
            
            return Excel::download(
                new AdminTemplateExport(),
                $filename
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while generating the template: ' . $e->getMessage());
        }
    }

    /**
     * Handle bulk upload of admin data
     */
    public function bulkUpload(Request $request)
    {
        $validated = $request->validate([
            'template' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            // Import the data
            Excel::import(new AdminTemplateImport(), $request->file('template'));

            $kashtre = Business::find(1);
            if ($kashtre?->sendsPasswordResetLink()) {
                $newUsers = User::where('password', '')->where('business_id', 1)->get();
                foreach ($newUsers as $user) {
                    Password::sendResetLink(['email' => $user->email]);
                }

                return redirect()->route('admins.index')->with('success', 'Admin data uploaded and processed successfully! Password reset emails have been sent to new users.');
            }

            return redirect()->route('admins.index')->with('success', 'Admin data uploaded and processed successfully! New users can sign in with the default password.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred during import: ' . $e->getMessage());
        }
    }
}
