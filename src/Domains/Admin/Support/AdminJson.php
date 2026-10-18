<?php

namespace LaRecipe\Domains\Admin\Support;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Sources\DTOs\Revision;

/**
 * The JSON shapes the admin app reads, in one place. Frontmatter and other
 * maps are sent as objects even when empty, so the app never has to tell an
 * empty list from an empty map.
 */
final class AdminJson
{
    public static function page(Page $page, ?string $url = null): array
    {
        return [
            'ref' => $page->ref->toArray(),
            'path' => $page->ref->path,
            'title' => $page->title,
            'description' => $page->description,
            'frontmatter' => self::map($page->frontmatter->toArray()),
            'body' => $page->body,
            'content_hash' => $page->contentHash,
            'updated_at' => $page->updatedAt?->format(DATE_ATOM),
            'provenance' => $page->provenance?->toArray(),
            'url' => $url,
        ];
    }

    /** A revision for lists: who, what, when and why, without the full payload. */
    public static function revision(Revision $revision): array
    {
        $data = $revision->toArray();
        unset($data['payload'], $data['conflict']);

        return $data + [
            'ticket' => self::ticket($revision->id),
            'is_conflict' => $revision->status === 'conflict',
        ];
    }

    /** A revision with everything it carries: its payload and, for a conflict, production's side. */
    public static function fullRevision(Revision $revision): array
    {
        $data = self::revision($revision);
        $data['payload'] = self::map($revision->payload);
        $data['conflict'] = $revision->conflict !== null ? self::map($revision->conflict) : null;

        return $data;
    }

    /** "0142": revision numbers are the ticket numbers of the review queue (DESIGN.md, the ticket). */
    public static function ticket(?string $id): ?string
    {
        return $id === null ? null : str_pad($id, 4, '0', STR_PAD_LEFT);
    }

    /** An associative array as a JSON object (an empty one included); lists stay lists. */
    public static function map(array $data): array|object
    {
        if ($data === []) {
            return (object) [];
        }

        foreach ($data as $key => $value) {
            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $data[$key] = self::map($value);
            }
        }

        return $data;
    }
}
