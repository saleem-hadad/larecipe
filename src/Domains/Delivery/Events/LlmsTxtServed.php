<?php

namespace LaRecipe\Domains\Delivery\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * An llms.txt index or an llms-full.txt file was served. Agents read these and
 * never run client-side analytics, so this is how hosts see that traffic.
 */
final class LlmsTxtServed
{
    /**
     * @param  string  $file  "llms.txt" or "llms-full.txt"
     * @param  ?string  $group  the group slug of a split file, null for a whole index
     */
    public function __construct(
        public readonly string $set,
        public readonly string $file,
        public readonly string $kind,
        public readonly ?string $version = null,
        public readonly ?string $locale = null,
        public readonly ?string $group = null,
        public readonly ?string $userAgent = null,
        public readonly ?PageRef $page = null,
    ) {}
}
