<?php

namespace App\Services\Clinical\Dispatch;

use App\Contracts\ModuleDispatcher;
use App\Services\Clinical\Facts\Fact;

/**
 * Default ModuleDispatcher binding today: Clinical, Imaging, and Inventory
 * all live in this one Laravel app, so there is no reason to round-trip
 * through HTTP to talk to ourselves. Invokes the receiver synchronously,
 * matching the app's current QUEUE_CONNECTION=sync behaviour.
 */
class LocalModuleDispatcher implements ModuleDispatcher
{
    public function __construct(private readonly LocalFactReceiverRegistry $registry)
    {
    }

    public function dispatch(Fact $fact): array
    {
        return $this->registry->handle($fact);
    }
}
