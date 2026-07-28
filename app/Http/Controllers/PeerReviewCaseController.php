<?php

namespace App\Http\Controllers;

class PeerReviewCaseController extends Controller
{
    public function index()
    {
        return view('imaging.peer-review-cases.index');
    }
}
