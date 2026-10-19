<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\DTOs\MovePlan;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * Counts what a set holds (for the plan a dry run prints) and validates every
 * page, so a move never copies content the target would refuse to serve.
 */
final class SourceInventory
{
    public function __construct(private readonly ValidatePageAction $validate) {}

    public function plan(string $set, WritableContentSource $source): MovePlan
    {
        $manifest = $source->manifest();
        $pages = [];
        $navigations = 0;
        $assets = 0;
        $defaultLocale = SetLayout::defaultLocale($manifest);

        foreach (SetLayout::versions($manifest) as $version) {
            $shared = ContentHashes::navigation($source->navigation($version, $defaultLocale));

            foreach (SetLayout::locales($manifest) as $locale) {
                foreach ($source->pages($version, $locale) as $ref) {
                    $pages[] = "{$version}/{$locale}/{$ref->path}";
                }

                $hash = ContentHashes::navigation($source->navigation($version, $locale));
                $navigations += $hash !== ContentHashes::ABSENT && ($locale === $defaultLocale || $hash !== $shared) ? 1 : 0;
            }
        }

        foreach (SetLayout::assetVersions($manifest) as $version) {
            foreach ($source->assets($version) as $path) {
                $assets++;
            }
        }

        return new MovePlan($set, $manifest !== null, $pages, $navigations, $assets);
    }

    public function validate(WritableContentSource $source): ValidationReport
    {
        $report = new ValidationReport;
        $manifest = $source->manifest();

        foreach (SetLayout::versions($manifest) as $version) {
            foreach (SetLayout::locales($manifest) as $locale) {
                foreach ($source->pages($version, $locale) as $ref) {
                    $page = $source->page($ref);

                    if ($page !== null) {
                        $report = $report->merge($this->validate->execute($page));
                    }
                }
            }
        }

        return $report;
    }
}
