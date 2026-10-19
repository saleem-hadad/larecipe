<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use Illuminate\Contracts\Config\Repository as Config;
use LaRecipe\Domains\Agents\DTOs\DoctorCheck;

/**
 * A database set runs behind a persistent cache, so a warm page never queries
 * the database (the Cached-Database Rule).
 */
final class CacheStoreCheck implements Check
{
    public function __construct(private readonly Config $config) {}

    public function run(DoctorContext $context): DoctorCheck
    {
        if ($context->set->config->driver !== 'database') {
            return DoctorCheck::skip('cache-store', 'Cache store', 'Repository sets read from files; no cache store is needed.');
        }

        $store = $context->set->config->cacheStore() ?? $this->config->get('cache.default');
        $driver = $this->config->get("cache.stores.{$store}.driver", $store);

        return in_array($driver, ['array', 'null'], true)
            ? DoctorCheck::fail('cache-store', 'Cache store', "The database set runs on the \"{$driver}\" cache store, which keeps nothing.", "Point sets.{$context->set->key}.source.cache (or larecipe.cache.store) at redis, database or file.")
            : DoctorCheck::pass('cache-store', 'Cache store', "The database set is cached in the \"{$store}\" store.");
    }
}
