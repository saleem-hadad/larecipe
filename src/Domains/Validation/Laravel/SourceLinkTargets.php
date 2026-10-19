<?php

namespace LaRecipe\Domains\Validation\Laravel;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Enums\Visibility;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Sources\Contracts\ContentSource;
use LaRecipe\Domains\Validation\Contracts\LinkTargets;
use LaRecipe\Domains\Validation\Services\CollectingLinkResolver;
use LaRecipe\Domains\Validation\Services\LinkPaths;

/**
 * Link targets for checking one page before it is written: reads the target
 * pages from the set's source on demand, rendering each one once for its anchors.
 */
final class SourceLinkTargets implements LinkTargets
{
    /** @var array<string, list<string>|false> */
    private array $anchors = [];

    public function __construct(
        private readonly DocSet $set,
        private readonly ContentSource $source,
        private readonly RenderPageAction $render,
    ) {}

    public function anchors(string $version, string $locale, string $path): ?array
    {
        $ref = new PageRef($this->set->key, $version, $locale, $path);

        if (! isset($this->anchors[$ref->key()])) {
            $page = $this->page($version, $locale, $path);
            $this->anchors[$ref->key()] = $page === null
                ? false
                : LinkPaths::anchorsIn($this->render->execute($page, new CollectingLinkResolver)->html);
        }

        return $this->anchors[$ref->key()] === false ? null : $this->anchors[$ref->key()];
    }

    public function isPrivate(string $version, string $locale, string $path): bool
    {
        if ($this->set->version($version)?->visibility === Visibility::Private) {
            return true;
        }

        return $this->page($version, $locale, $path)?->frontmatter->visibility() === Visibility::Private;
    }

    public function paths(string $version, string $locale): array
    {
        $paths = [];

        foreach (array_unique([$locale, $this->set->defaultLocale()->code]) as $code) {
            foreach ($this->source->pages($version, $code) as $ref) {
                $paths[$ref->path] = true;
            }
        }

        return array_keys($paths);
    }

    /** The page a reader gets at a path: the translation, or the original until there is one. */
    private function page(string $version, string $locale, string $path): ?Page
    {
        return $this->source->page(new PageRef($this->set->key, $version, $locale, $path))
            ?? $this->source->page(new PageRef($this->set->key, $version, $this->set->defaultLocale()->code, $path));
    }
}
