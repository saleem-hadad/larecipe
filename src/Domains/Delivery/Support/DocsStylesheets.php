<?php

namespace LaRecipe\Domains\Delivery\Support;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;

/**
 * The stylesheets a set lists under assets.css in larecipe.json (a theme.css
 * next to it, for small overrides: ARCHITECTURE.md 9.1), as <link> tags that
 * go right after LaRecipe's own CSS.
 *
 * They are served by the docs asset route, with the file's content hash in
 * the URL, so a changed file gets a new URL and the old one can be cached for
 * good. A listed file that does not exist gets no tag (larecipe:validate
 * reports it as assets.missing).
 */
final class DocsStylesheets
{
    /** The length of the content fingerprint in a stylesheet URL (?v=...). */
    public const FINGERPRINT_LENGTH = 12;

    public function __construct(
        private readonly SourceResolver $sources,
        private readonly DocsUrls $urls,
    ) {}

    public function tags(DocSet $set, string $version): string
    {
        $source = $this->sources->for($set->key);
        $tags = [];

        foreach ($set->manifest->stylesheets() as $file) {
            $asset = $source->asset($version, $file);

            if ($asset === null) {
                continue;
            }

            $url = $this->urls->assetPath($set, $version, $asset->path).'?v='.self::fingerprint($asset->hash ?? hash('sha256', $asset->contents()));
            $tags[] = '<link rel="stylesheet" href="'.e($url).'">';
        }

        return implode("\n", $tags);
    }

    /** The URL fingerprint for a content hash ("sha256:ab12..." or a bare hex digest). */
    public static function fingerprint(string $hash): string
    {
        return substr((string) preg_replace('/^[a-z0-9]+:/', '', $hash), 0, self::FINGERPRINT_LENGTH);
    }
}
