<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Onboarding\Services\JsonSettings;
use LaRecipe\Domains\Sources\Actions\GetNavigationAction;
use LaRecipe\Domains\Sources\Actions\ListPageSummariesAction;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\FileChange;

/**
 * Writes the navigation LaRecipe derives from folders and frontmatter to
 * navigation.json, for a team that wants explicit control over it. A version
 * that already has a navigation.json is frozen already and left alone.
 */
final class FreezeNavigationAction
{
    public const SCHEMA = 'https://larecipe.saleem.dev/schemas/3.x/navigation.json';

    public function __construct(
        private readonly ListPageSummariesAction $pages,
        private readonly GetNavigationAction $navigation,
        private readonly JsonSettings $json,
    ) {}

    public function execute(Workspace $workspace, CatalogSet $set, string $version, string $locale): FileChange
    {
        $parts = array_filter([
            rtrim((string) $set->docsPath, '/'),
            $set->versioned ? $version : '',
            $locale !== $set->defaultLocale ? $locale : '',
        ], fn (string $part) => $part !== '');
        $file = implode('/', $parts).'/navigation.json';

        if ($workspace->exists($file)) {
            return $workspace->skip($file, 'This navigation is already frozen. Edit the file, or delete it to derive it again.');
        }

        $tree = $this->navigation->execute($set->key, $version, $locale, $this->pages->execute($set->key, $version, $locale));

        return $workspace->write($file, $this->json->encode(['$schema' => self::SCHEMA, ...$tree->toJson()]));
    }
}
