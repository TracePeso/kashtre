<?php

namespace App\Services\Clinical\Dispatch;

use App\Services\Clinical\Facts\Fact;
use RuntimeException;

/**
 * Explicit receiver map for the 'local' dispatch driver — deliberately not
 * Laravel's Event/Listener auto-discovery, since this app already disables
 * that (see EventServiceProvider::shouldDiscoverEvents). Each module that
 * wants to receive facts registers a callable here, keyed by
 * "{targetModule}.{factType}", from its own service provider's boot().
 */
class LocalFactReceiverRegistry
{
    /** @var array<string, callable(array<string, mixed>): array<string, mixed>> */
    private array $receivers = [];

    public function register(string $targetModule, string $factType, callable $receiver): void
    {
        $this->receivers["{$targetModule}.{$factType}"] = $receiver;
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Fact $fact): array
    {
        $key = "{$fact->targetModule()}.{$fact->factType()}";
        $receiver = $this->receivers[$key] ?? null;

        if ($receiver === null) {
            throw new RuntimeException("No local receiver registered for fact [{$key}]. Register one in the owning module's service provider boot().");
        }

        return $receiver($fact->toPayload());
    }
}
