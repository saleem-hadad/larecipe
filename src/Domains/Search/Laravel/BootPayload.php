<?php

namespace LaRecipe\Domains\Search\Laravel;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Search\Services\SearchDrivers;

/**
 * Adds search to the boot payload React reads:
 *
 *   search: { driver: "client", index_url: "/docs/_search/{audience}.{fingerprint}.json", endpoint: "/docs/_search", report_url: "/docs/_search/report" }
 *
 * index_url is this reader's own index variant. It is null for the "server"
 * driver and custom drivers; the UI then queries the endpoint instead, and
 * the endpoint emits the search events itself, so report_url is null too.
 */
final class BootPayload
{
    public function __construct(
        private readonly Reader $reader,
        private readonly SearchUrls $urls,
        private readonly SearchDrivers $drivers,
    ) {}

    /** @return array{search: array{driver: string, index_url: ?string, endpoint: string, report_url: ?string}} */
    public function __invoke(DocSet $set, array $payload): array
    {
        $version = (string) ($payload['version'] ?? $set->defaultVersion()->id);
        $locale = (string) ($payload['locale']['code'] ?? $set->defaultLocale()->code);
        $driver = $this->drivers->name($set->key);

        $indexUrl = $driver === SearchDrivers::CLIENT
            ? $this->urls->index($set, $version, $locale, $this->reader->audience($set, $version, $locale))
            : null;

        return ['search' => [
            'driver' => $driver,
            'index_url' => $indexUrl,
            'endpoint' => $this->urls->endpoint($set, $version, $locale),
            'report_url' => $indexUrl !== null ? $this->urls->report($set, $version, $locale) : null,
        ]];
    }
}
