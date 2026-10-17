<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\Codebase;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\DTOs\CatalogPage;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\SourceSuggestion;
use LaRecipe\Domains\Sync\Services\CodeMentions;
use LaRecipe\Domains\Sync\Services\GlobMatcher;

/**
 * Proposes `sources:` for pages that do not declare any, by matching what the
 * page mentions in inline code (class names, artisan commands, route names,
 * live reference components) against where those things are defined in this
 * project. A page without sources is invisible to drift detection; this
 * gets a team from zero to useful in one command.
 */
final class SuggestSourcesAction
{
    public function __construct(
        private readonly DocsCatalog $catalog,
        private readonly Codebase $codebase,
        private readonly CodeMentions $mentions,
        private readonly GlobMatcher $globs,
    ) {}

    /** @return list<SourceSuggestion> */
    public function execute(?string $set = null, ?string $path = null, bool $includePagesWithSources = false): array
    {
        $suggestions = [];

        foreach ($this->sets($set) as $catalogSet) {
            foreach ($this->catalog->pages($catalogSet->key, $catalogSet->defaultVersion, $catalogSet->defaultLocale) as $page) {
                if ($path !== null && $page->ref->path !== trim($path, '/')) {
                    continue;
                }

                if ($page->sources !== [] && ! $includePagesWithSources) {
                    continue;
                }

                array_push($suggestions, ...$this->suggest($page));
            }
        }

        return $suggestions;
    }

    /** @return list<SourceSuggestion> */
    private function suggest(CatalogPage $page): array
    {
        $byFile = [];

        foreach ($this->mentions->find((string) $page->body) as $mention) {
            $file = match ($mention['kind']) {
                'class' => $this->codebase->classFile($mention['name']),
                'model' => $this->codebase->classFile(str_contains($mention['name'], '\\') ? $mention['name'] : 'App\\Models\\'.$mention['name']),
                'event' => $this->codebase->classFile(str_contains($mention['name'], '\\') ? $mention['name'] : 'App\\Events\\'.$mention['name']),
                'command' => $this->codebase->commandFile($mention['name']),
                'route' => $this->codebase->routeFile($mention['name']),
                default => null,
            };

            if ($file === null || $this->alreadyCovered($page, $file)) {
                continue;
            }

            $byFile[$file]['kind'] ??= in_array($mention['kind'], ['model', 'event'], true) ? 'class' : $mention['kind'];
            $byFile[$file]['evidence'][] = $mention['evidence'];
        }

        ksort($byFile);
        $suggestions = [];

        foreach ($byFile as $file => $data) {
            $suggestions[] = new SourceSuggestion($page, $file, $data['kind'], array_values(array_unique($data['evidence'])));
        }

        return $suggestions;
    }

    private function alreadyCovered(CatalogPage $page, string $file): bool
    {
        foreach ($page->sourcePatterns() as $pattern) {
            if ($this->globs->matches($pattern, $file)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<CatalogSet> */
    private function sets(?string $set): array
    {
        if ($set === null) {
            return $this->catalog->sets();
        }

        $found = $this->catalog->set($set);

        return $found === null ? [] : [$found];
    }
}
