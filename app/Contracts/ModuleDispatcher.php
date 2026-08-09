<?php

namespace App\Contracts;

use App\Services\Clinical\Facts\Fact;

/**
 * Clinical Module Chunk 0: transport-agnostic cross-module dispatch.
 * Bound to LocalModuleDispatcher (in-process, same app) by default;
 * swap to HttpModuleDispatcher via config('services.dispatch.driver')
 * once a target module moves to its own server — no caller of this
 * contract changes, only the binding + module_endpoints config.
 */
interface ModuleDispatcher
{
    /**
     * @return array<string, mixed> the receiver's response payload
     */
    public function dispatch(Fact $fact): array;
}
