<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * The checks a navigation or manifest write needs before it lands: entries
 * that point at no page, duplicate versions or locales, and links that are not
 * http, https, mailto or relative (links.unsafe). Pages are validated by
 * the Validation domain's ValidatePageAction; these two files have no Action
 * there yet, so their write-time checks live here, using the same report shape.
 */
final class StructureValidator
{
    /** @param list<string> $knownPaths the paths of the pages in this version and locale */
    public function navigation(NavigationTree $tree, array $knownPaths, string $where): ValidationReport
    {
        $known = array_flip($knownPaths);
        $issues = array_map(fn (string $href) => ValidationIssue::unsafeLink($href, $where), $tree->refusedLinks);

        foreach ($tree->pagePaths() as $path) {
            if (! isset($known[$path])) {
                $issues[] = new ValidationIssue(
                    ValidationIssue::WARNING,
                    'navigation.page.missing',
                    "The navigation lists [{$path}], but no such page exists.",
                    $where,
                    fix: 'Create the page, or remove it from the navigation.',
                );
            }
        }

        return new ValidationReport($issues, 0);
    }

    public function manifest(Manifest $manifest): ValidationReport
    {
        $issues = array_map(fn (string $href) => ValidationIssue::unsafeLink($href, 'larecipe.json'), $manifest->refusedLinks());

        foreach (['versions' => fn ($v) => $v->id, 'locales' => fn ($l) => $l->code] as $key => $id) {
            $ids = array_map($id, $manifest->{$key});

            if (in_array('', $ids, true)) {
                $issues[] = new ValidationIssue(ValidationIssue::ERROR, "manifest.{$key}.invalid", "Every entry in {$key} needs an id.", 'larecipe.json');
            }

            if (count($ids) !== count(array_unique($ids))) {
                $issues[] = new ValidationIssue(ValidationIssue::ERROR, "manifest.{$key}.duplicate", "larecipe.json lists the same {$key} entry twice.", 'larecipe.json');
            }
        }

        if (count(array_filter($manifest->versions, fn ($v) => $v->default)) > 1) {
            $issues[] = new ValidationIssue(ValidationIssue::ERROR, 'manifest.versions.default', 'Only one version may be the default.', 'larecipe.json');
        }

        return new ValidationReport($issues, 0);
    }
}
