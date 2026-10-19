<?php

namespace LaRecipe\Domains\Access\DTOs;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * A temporary, signed grant to read one page. The token goes in the page URL's
 * "share" query parameter.
 */
final readonly class ShareLink
{
    /** The query parameter that carries the token on any URL of the page. */
    public const QUERY = 'share';

    public function __construct(
        public PageRef $ref,
        public string $token,
        public DateTimeImmutable $expiresAt,
    ) {}
}
