<?php

namespace App\Http\Controllers;

class ImagingOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('imaging.orders.index');
    }
}
