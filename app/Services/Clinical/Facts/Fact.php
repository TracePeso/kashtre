<?php

namespace App\Services\Clinical\Facts;

/**
 * Base for every cross-module "fact" the Clinical Module emits or
 * receives. Concrete subclasses define the exact JSON shape from the
 * relevant Interface Control Document (docs/clinical module/*.md) — that
 * shape is the wire contract regardless of which ModuleDispatcher driver
 * carries it (in-process call today, real HTTP later).
 */
abstract class Fact
{
    /**
     * The receiving module: 'imaging', 'inventory', or 'lims'.
     */
    abstract public function targetModule(): string;

    /**
     * The event/fact type, e.g. 'diagnostic-order-placed'. Used by
     * LocalFactReceiverRegistry to look up the in-process receiver and,
     * under the http driver, appended to the target module's base URL.
     */
    abstract public function factType(): string;

    /**
     * The exact payload shape documented in the relevant ICD.
     *
     * @return array<string, mixed>
     */
    abstract public function toPayload(): array;
}
