<?php

namespace LaRecipe\Domains\Access\DTOs;

/**
 * How one reader sees one page, as far as caching and indexing are concerned.
 *
 * A response that only this reader may see (a private page, a private version,
 * a page opened with a share link, or a page whose gate blocks granted the
 * reader something guests do not get) is personal: shared caches never store it
 * and search engines never index it.
 */
final readonly class ReaderView
{
    /**
     * @param  bool  $gated  the page has gate blocks, so its content varies by reader
     * @param  bool  $public  every reader, guests included, may see the page
     * @param  bool  $shared  the page was opened with a share link
     */
    public function __construct(
        public Audience $audience,
        public bool $gated = false,
        public bool $public = true,
        public bool $shared = false,
    ) {}

    public function isPersonal(): bool
    {
        return $this->shared || ! $this->public || ! $this->audience->isGuest();
    }
}
