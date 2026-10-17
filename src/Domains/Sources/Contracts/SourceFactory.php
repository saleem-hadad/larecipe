<?php

namespace LaRecipe\Domains\Sources\Contracts;

/**
 * Builds a source for a doc set with a driver other than the one it is
 * configured with. Source moves need both ends at once: the configured source
 * and the one the set is moving to.
 */
interface SourceFactory
{
    /** @param array<string, mixed> $options the driver's source config (path, connection, cache, disk) */
    public function make(string $set, string $driver, array $options = []): WritableContentSource;
}
