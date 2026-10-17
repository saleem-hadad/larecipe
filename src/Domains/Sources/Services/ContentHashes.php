<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;

/**
 * Normalized content hashes for the three canonical files.
 *
 * Pages hash their frontmatter and body (Page::hash). Navigation and manifests
 * hash a normalized JSON form, so formatting and key order never count as a
 * change. The same hashes drive docs-migration conflict checks (basedOn) and
 * the proof step of a source move.
 */
final class ContentHashes
{
    /** The basedOn value for "this did not exist when the change was written". */
    public const ABSENT = 'absent';

    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public static function page(?Page $page): string
    {
        return $page?->contentHash ?? self::ABSENT;
    }

    public static function navigation(?NavigationTree $tree): string
    {
        return $tree === null ? self::ABSENT : 'sha256:'.hash('sha256', (string) json_encode($tree->toJson(), self::FLAGS));
    }

    public static function manifest(?Manifest $manifest): string
    {
        return $manifest === null ? self::ABSENT : self::manifestArray($manifest->toArray());
    }

    public static function manifestArray(array $manifest): string
    {
        return 'sha256:'.hash('sha256', (string) json_encode(self::sortKeys($manifest), self::FLAGS));
    }

    public static function asset(string $contents): string
    {
        return 'sha256:'.hash('sha256', $contents);
    }

    /** Sort object keys recursively; list order is content and stays. */
    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::sortKeys(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
