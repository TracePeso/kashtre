<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class StaffCategoryController extends Controller
{
    public function index()
    {
        abort_unless(Auth::user()?->business_id === 1, 403);

        return view('staff-categories.index');
    }
}
