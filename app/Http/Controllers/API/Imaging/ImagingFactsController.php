<?php

namespace App\Http\Controllers\API\Imaging;

use App\Http\Controllers\Controller;
use App\Services\Clinical\Integration\ImagingOrderReceiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RIS Amendment v2.6, Chunk 8 extension — the real endpoint
 * HttpModuleDispatcher posts to (DISPATCH_DRIVER=http) once Clinical and
 * Imaging are no longer the same process. Same shared-secret idiom as
 * the rest of the imaging.api group (VerifyImagingApiKey), same payload
 * shape as the local driver — this is a thin transport shim over
 * ImagingOrderReceiver, not a second copy of the order-creation logic.
 */
class ImagingFactsController extends Controller
{
    public function handle(Request $request, string $factType, ImagingOrderReceiver $receiver): JsonResponse
    {
        return match ($factType) {
            'diagnostic-order-placed' => response()->json($receiver->handle($request->all())),
            default => response()->json(['error' => "Unknown fact type: {$factType}"], 404),
        };
    }
}
