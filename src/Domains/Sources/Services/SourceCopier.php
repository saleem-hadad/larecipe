<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\DTOs\WriteContext;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * Copies a whole doc set from one source to another in the canonical format:
 * the manifest first (it decides the version folders), then every version and
 * locale's pages and navigation, then the assets. Identical content is
 * skipped, so copying twice writes nothing the second time.
 *
 * Two details keep a round trip lossless: deeper pages are written first, so a
 * folder's own page lands as folder/index.md in a repository (relative links
 * keep resolving); and a locale's navigation is copied only when it differs
 * from the default locale's, which both drivers fall back to.
 */
final class SourceCopier
{
    public function copy(WritableContentSource $from, WritableContentSource $to, Provenance $by, WriteContext $context): void
    {
        $manifest = $from->manifest();

        if ($manifest !== null && ContentHashes::manifest($manifest) !== ContentHashes::manifest($to->manifest())) {
            $to->writeManifest($manifest, $by, WriteMode::Publish, $context);
        }

        $defaultLocale = SetLayout::defaultLocale($manifest);

        foreach (SetLayout::versions($manifest) as $version) {
            $sharedNavigation = ContentHashes::navigation($from->navigation($version, $defaultLocale));

            foreach (SetLayout::locales($manifest) as $locale) {
                foreach ($this->deepestFirst($from->pages($version, $locale)) as $ref) {
                    $page = $from->page($ref);

                    if ($page !== null && $page->contentHash !== ContentHashes::page($to->page($ref))) {
                        $to->writePage($page, $by, WriteMode::Publish, $context);
                    }
                }

                $navigation = $from->navigation($version, $locale);
                $hash = ContentHashes::navigation($navigation);
                $inherited = $locale !== $defaultLocale && $hash === $sharedNavigation;

                if ($navigation !== null && ! $inherited && $hash !== ContentHashes::navigation($to->navigation($version, $locale))) {
                    $to->writeNavigation($version, $locale, $navigation, $by, WriteMode::Publish, $context);
                }
            }
        }

        foreach (SetLayout::assetVersions($manifest) as $version) {
            foreach ($from->assets($version) as $path) {
                $asset = $from->asset($version, $path);

                if ($asset !== null && ContentHashes::asset($asset->contents()) !== ContentHashes::asset((string) $to->asset($version, $path)?->contents())) {
                    $to->writeAsset($version, $path, $asset->contents());
                }
            }
        }
    }

    /**
     * @param  iterable<PageRef>  $refs
     * @return list<PageRef>
     */
    private function deepestFirst(iterable $refs): array
    {
        $refs = is_array($refs) ? $refs : iterator_to_array($refs, false);

        usort($refs, fn (PageRef $a, PageRef $b) => [substr_count($b->path, '/'), $a->path] <=> [substr_count($a->path, '/'), $b->path]);

        return $refs;
    }
}
