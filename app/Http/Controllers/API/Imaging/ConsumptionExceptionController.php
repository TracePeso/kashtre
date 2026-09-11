<?php

namespace App\Http\Controllers\API\Imaging;

use App\Http\Controllers\Controller;
use App\Models\ImagingConsumptionException;
use App\Services\Imaging\ConsumptionAttributionService;
use Illuminate\Http\Request;

/**
 * RIS Amendment v2.6, Chunk 8: /api/v1/imaging/consumption-exceptions —
 * the same View/Resolve pair as ListImagingConsumptionExceptions (Chunk 6),
 * for an external consumer that wants to track or resolve these itself
 * rather than through the Kashtre UI.
 */
class ConsumptionExceptionController extends Controller
{
    public function index(Request $request)
    {
        $exceptions = ImagingConsumptionException::query()
            ->with(['study:id,accession_number,business_id,client_id'])
            ->when($request->filled('business_id'), function ($q) use ($request) {
                $q->whereHas('study', fn ($s) => $s->where('business_id', (int) $request->business_id));
            })
            ->when($request->filled('resolved'), fn ($q) => $q->where('resolved', $request->boolean('resolved')))
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return response()->json($exceptions);
    }

    public function resolve(ImagingConsumptionException $consumptionException, Request $request, ConsumptionAttributionService $service)
    {
        $data = $request->validate([
            'user_id' => 'required|integer',
        ]);

        $service->resolveException($consumptionException, (int) $data['user_id']);

        return response()->json($consumptionException->fresh());
    }
}
