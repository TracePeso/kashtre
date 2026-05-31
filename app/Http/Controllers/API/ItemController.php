<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ItemController extends Controller
{
    /**
     * Return items for a given business (for insurer portal).
     */
    public function index(Request $request, int $businessId)
    {
        Log::info('API/ItemController@index: fetching items for business', [
            'business_id' => $businessId,
        ]);

        $query = Item::where('business_id', $businessId);

        // Optional filter by codes (if needed later)
        $codes = (array) $request->input('codes', []);
        if (!empty($codes)) {
            $query->whereIn('code', $codes);
        }

        $items = $query
            ->orderBy('name')
            ->get(['id', 'name', 'generic_name', 'category', 'code', 'type']);

        Log::info('API/ItemController@index: items fetched', [
            'business_id' => $businessId,
            'count' => $items->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}

