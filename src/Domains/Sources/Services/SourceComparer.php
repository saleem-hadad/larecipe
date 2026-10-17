<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Sources\Contracts\WritableContentSource;

/**
 * The proof step of a source move: re-read the target and compare normalized
 * content hashes for the manifest, every page, every navigation tree and every
 * asset. Formatting, key order and storage never count; content does.
 */
final class SourceComparer
{
    /** @return list<string> one line per difference; empty means identical */
    public function compare(WritableContentSource $expected, WritableContentSource $actual): array
    {
        $mismatches = [];
        $manifest = $expected->manifest();

        if (ContentHashes::manifest($manifest) !== ContentHashes::manifest($actual->manifest())) {
            $mismatches[] = 'larecipe.json differs.';
        }

        foreach (SetLayout::versions($manifest) as $version) {
            foreach (SetLayout::locales($manifest) as $locale) {
                $want = $this->pageHashes($expected, $version, $locale);
                $have = $this->pageHashes($actual, $version, $locale);

                foreach (array_diff_key($want, $have) as $path => $hash) {
                    $mismatches[] = "Page {$version}/{$locale}/{$path} is missing.";
                }

                foreach (array_diff_key($have, $want) as $path => $hash) {
                    $mismatches[] = "Page {$version}/{$locale}/{$path} is extra.";
                }

                foreach (array_intersect_key($want, $have) as $path => $hash) {
                    if ($have[$path] !== $hash) {
                        $mismatches[] = "Page {$version}/{$locale}/{$path} differs.";
                    }
                }

                if (ContentHashes::navigation($expected->navigation($version, $locale)) !== ContentHashes::navigation($actual->navigation($version, $locale))) {
                    $mismatches[] = "Navigation {$version}/{$locale} differs.";
                }
            }
        }

        foreach (SetLayout::assetVersions($manifest) as $version) {
            $want = $this->assetHashes($expected, $version);
            $have = $this->assetHashes($actual, $version);

            foreach ($want as $path => $hash) {
                if (($have[$path] ?? null) !== $hash) {
                    $mismatches[] = "Asset {$version}/{$path} ".(isset($have[$path]) ? 'differs.' : 'is missing.');
                }
            }
        }

        return $mismatches;
    }

    /** @return array<string, string> */
    private function pageHashes(WritableContentSource $source, string $version, string $locale): array
    {
        $hashes = [];

        foreach ($source->pages($version, $locale) as $ref) {
            $hashes[$ref->path] = ContentHashes::page($source->page($ref));
        }

        return $hashes;
    }

    /** @return array<string, string> */
    private function assetHashes(WritableContentSource $source, string $version): array
    {
        $hashes = [];

        foreach ($source->assets($version) as $path) {
            $hashes[$path] = ContentHashes::asset((string) $source->asset($version, $path)?->contents());
        }

        return $hashes;
    }
}
