<?php

namespace LaRecipe\Domains\Admin\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * The text a reviewer diffs: a page as its Markdown file would read
 * (frontmatter block, then body), and navigation or a manifest as the JSON
 * file it is. Line-based diffs of these are what a pull request would show.
 */
final class SourceText
{
    /** @param array<string, mixed> $frontmatter */
    public static function page(array $frontmatter, string $body): string
    {
        if ($frontmatter === []) {
            return $body;
        }

        return "---\n".Yaml::dump($frontmatter, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)."---\n\n".ltrim($body, "\n");
    }

    public static function json(mixed $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
}
