<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Settings → Clinical Dictionaries. A thin shell around the Livewire
 * component; the dictionary in the query string just selects the initial tab so
 * a link can point straight at one.
 */
class ClinicalDictionariesController extends Controller
{
    public function index(Request $request)
    {
        // Empty string, never null: Livewire assigns a mount argument onto the
        // matching public property before mount() runs, so a null here is a
        // TypeError against `public string $dictionary` and mount()'s nullable
        // parameter never gets a chance to default it.
        return view('clinical.dictionaries.index', [
            'dictionary' => (string) $request->query('dictionary', ''),
        ]);
    }
}
