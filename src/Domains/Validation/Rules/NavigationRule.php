<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Validation\Contracts\SetRule;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\DTOs\VersionSnapshot;
use LaRecipe\Domains\Validation\Services\SchemaValidator;
use LaRecipe\Domains\Validation\Services\Suggest;

/**
 * navigation.schema: navigation.json that is not valid JSON or not the shape
 * the schema describes.
 * navigation.missing-page: navigation entries without a page.
 * pages.orphan: pages a navigation.json leaves out (and does not hide), which
 * readers can only reach by URL.
 * links.unsafe: links that are not http, https, mailto or relative, which
 * pages leave out (SafeHref).
 *
 * Zero-config navigation lists every page by construction, so it has no
 * orphans; a translation that borrows its version's navigation.json falls
 * back to the default language for missing pages, so it has no missing ones.
 */
final class NavigationRule implements SetRule
{
    public function __construct(private readonly SchemaValidator $schemas) {}

    public function check(DocsSnapshot $docs): array
    {
        $issues = [];

        foreach ($docs->versions as $version) {
            array_push($issues, ...$this->version($version));
        }

        return $issues;
    }

    /** @return list<ValidationIssue> */
    private function version(VersionSnapshot $version): array
    {
        $file = $version->navigationFile;

        if ($version->navigationError !== null) {
            return [ValidationIssue::error('navigation.schema', 'navigation.json is not valid JSON: '.$version->navigationError, file: $file, fix: 'Fix the JSON syntax.')];
        }

        if ($version->navigation === null || $version->sharedNavigation) {
            return [];
        }

        $issues = [];

        foreach ($this->schemas->errors(SchemaValidator::NAVIGATION, $version->navigation) as $error) {
            $issues[] = ValidationIssue::error('navigation.schema', 'navigation.json '.$error, file: $file, fix: 'See the navigation schema: '.SchemaValidator::NAVIGATION.'.schema.json.');
        }

        $tree = NavigationTree::fromJson($version->navigation);
        $listed = $tree->pagePaths();
        $paths = array_keys($version->pages);

        foreach ($tree->refusedLinks as $href) {
            $issues[] = ValidationIssue::unsafeLink($href, $file);
        }

        foreach ($listed as $path) {
            if (! isset($version->pages[$path])) {
                $meant = Suggest::closest($path, $paths);
                $issues[] = ValidationIssue::error(
                    'navigation.missing-page',
                    "The navigation lists \"{$path}\", but there is no such page.",
                    $version->pageId($path),
                    null,
                    $meant !== null ? "Change the entry to \"{$meant}\"." : 'Remove the entry, or write the page.',
                    $file,
                );
            }
        }

        foreach ($version->pages as $path => $page) {
            if (! in_array($path, $listed, true) && ! $page->page->frontmatter->hidden()) {
                $issues[] = ValidationIssue::warning(
                    'pages.orphan',
                    "The page \"{$path}\" is not in the navigation, so readers can only reach it by URL.",
                    $version->pageId($path),
                    null,
                    "Add \"{$path}\" to navigation.json, or set hidden: true in its frontmatter.",
                    $page->file,
                );
            }
        }

        return $issues;
    }
}
