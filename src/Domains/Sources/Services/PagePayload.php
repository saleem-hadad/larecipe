<?php

namespace LaRecipe\Domains\Sources\Services;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;

/**
 * A page as plain arrays, for caches. Title and content hash are derived again
 * on the way back (Page::make), so they can never drift from the content.
 */
final class PagePayload
{
    public static function toArray(Page $page): array
    {
        return [
            'ref' => $page->ref->toArray(),
            'frontmatter' => $page->frontmatter->toArray(),
            'body' => $page->body,
            'updated_at' => $page->updatedAt?->format(DATE_ATOM),
            'provenance' => $page->provenance?->toArray(),
        ];
    }

    public static function fromArray(array $data): Page
    {
        $ref = $data['ref'];

        return Page::make(
            new PageRef($ref['set'], $ref['version'], $ref['locale'], $ref['path']),
            new Frontmatter((array) ($data['frontmatter'] ?? [])),
            (string) ($data['body'] ?? ''),
            isset($data['updated_at']) ? new DateTimeImmutable((string) $data['updated_at']) : null,
            is_array($data['provenance'] ?? null) ? Provenance::fromArray($data['provenance']) : null,
        );
    }
}
