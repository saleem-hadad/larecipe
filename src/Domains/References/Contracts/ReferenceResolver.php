<?php

namespace LaRecipe\Domains\References\Contracts;

use LaRecipe\Domains\References\DTOs\Reference;

/**
 * Reads one kind of fact from the host app's public shape, for a live reference
 * component (<Route>, <Artisan>, <Model>, <Event>).
 *
 * The No-Secrets Rule applies to every implementation: a resolver reads route
 * definitions, command signatures, model schemas and event payloads, and never
 * configuration or environment values. There is no <Config> and no <Env>.
 */
interface ReferenceResolver
{
    /** "route", "artisan", "model" or "event". */
    public function kind(): string;

    /** null means the target no longer exists (a references.missing error). */
    public function resolve(string $target, array $props = []): ?Reference;

    /** Changes whenever the surface this resolver reads changes, so cached pages re-render. */
    public function fingerprint(): string;
}
