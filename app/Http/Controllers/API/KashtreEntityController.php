<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\KashtreEntityService;
use Illuminate\Http\Request;

class KashtreEntityController extends Controller
{
    public function __construct(
        private readonly KashtreEntityService $kashtreEntities,
    ) {}

    /**
     * List organisations currently onboarded on Kashtre.
     */
    public function index(Request $request)
    {
        $registered = $request->has('registered_as_supplier')
            ? $request->boolean('registered_as_supplier')
            : null;

        $activelyUtilizing = $request->has('actively_utilizing')
            ? $request->boolean('actively_utilizing')
            : null;

        $entities = $this->kashtreEntities->registry(
            registeredAsSupplier: $registered,
            activelyUtilizing: $activelyUtilizing,
            search: $request->string('search')->toString() ?: null,
        );

        return response()->json([
            'data' => $entities,
            'meta' => [
                'count' => $entities->count(),
                'description' => 'Organisations onboarded on Kashtre (excludes the Kashtre super-admin organisation).',
            ],
        ]);
    }

    public function show(string $uuid)
    {
        $entity = $this->kashtreEntities->findByUuid($uuid);

        if (! $entity) {
            return response()->json(['message' => 'Kashtre entity not found.'], 404);
        }

        return response()->json([
            'data' => $this->kashtreEntities->toRegistryArray($entity),
        ]);
    }
}
