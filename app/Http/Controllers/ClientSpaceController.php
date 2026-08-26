<?php

namespace App\Http\Controllers;

use App\Models\ClientSpace;
use Illuminate\Http\Request;

class ClientSpaceController extends Controller
{
    public function index()
    {
        return view('client-spaces.index');
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(ClientSpace $clientSpace)
    {
        //
    }

    public function edit(ClientSpace $clientSpace)
    {
        //
    }

    public function update(Request $request, ClientSpace $clientSpace)
    {
        //
    }

    public function destroy(ClientSpace $clientSpace)
    {
        //
    }
}
