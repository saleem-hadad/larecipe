<?php

namespace LaRecipe\Domains\Delivery\Services;

use DateTimeImmutable;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\Domains\Markdown\DTOs\RenderedPage;
use LaRecipe\Domains\Markdown\DTOs\TocItem;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\LaRecipe;

/**
 * Shapes a rendered page for each audience: the Blade prerender, the boot
 * payload React reads, and the JSON payload for client-side navigation.
 *
 * All three come from the same data, so the server HTML and the React UI can
 * never disagree about what is on the page.
 */
final class PagePresenter
{
    /** @var list<\Closure(DocSet, array): array> */
    private array $extenders = [];

    public function __construct(
        private readonly DocsUrls $urls,
        private readonly PageCatalog $catalog,
        private readonly PageAccess $access,
        private readonly SourceResolver $sources,
        private readonly Assets $assets,
        private readonly LaRecipe $larecipe,
    ) {}

    /**
     * Let other domains add to the boot payload (search index URL, Ask AI,
     * feedback endpoint) without Delivery depending on them.
     *
     * @param  \Closure(DocSet, array): array  $extender  receives the payload, returns additions
     */
    public function extend(\Closure $extender): void
    {
        $this->extenders[] = $extender;
    }

    /**
     * @param  array<string, PageSummary>  $visible  the reader's visible pages in this version and locale
     */
    public function present(DocSet $set, Page $page, RenderedPage $rendered, array $visible, ?string $fallbackFromLocale = null): array
    {
        $ref = $page->ref;
        $navigation = $this->navigation($set, $ref, $visible);
        $sequence = $this->catalog->sequence($navigation);
        $position = array_search($ref->path, $sequence, true);

        $payload = [
            'set' => $this->set($set, $ref),
            'page' => [
                'path' => $ref->path,
                'title' => $page->title,
                'description' => $page->description,
                'url' => $this->urls->page($set, $ref),
                'path_url' => $this->urls->pagePath($set, $ref),
                'markdown_url' => $this->urls->page($set, $ref, true),
                'edit_url' => $this->editUrl($set, $ref),
                'updated_at' => $page->updatedAt?->format(DATE_ATOM),
                'fresh' => $page->updatedAt !== null && $page->updatedAt > new DateTimeImmutable('-7 days'),
                'provenance' => $set->manifest->showsProvenance() ? $page->provenance?->toArray() : null,
                'layout' => $page->frontmatter->layout(),
                'badge' => $page->frontmatter->get('badge'),
                'meta' => $page->frontmatter->meta(),
                'inserts' => $page->frontmatter->inserts(),
                'toc' => array_map(fn (TocItem $item) => $item->toArray(), $rendered->toc),
                'components' => $rendered->components,
                'previous' => $position !== false && $position > 0 ? $this->neighbour($set, $ref, $sequence[$position - 1], $visible) : null,
                'next' => $position !== false && isset($sequence[$position + 1]) ? $this->neighbour($set, $ref, $sequence[$position + 1], $visible) : null,
                'fallback_from_locale' => $fallbackFromLocale,
                'noindex' => $page->frontmatter->noindex(),
            ],
            'navigation' => $navigation,
            'version' => $ref->version,
            'locale' => [
                'code' => $ref->locale,
                'dir' => ($set->locale($ref->locale) ?? $set->defaultLocale())->dir->value,
            ],
            'urls' => [
                'base' => $this->urls->base($set),
                'llms' => $this->urls->llms($set, $set->isVersioned() ? $ref->version : null),
                'mcp' => $set->config->setting('mcp.web.enabled', true) ? $this->urls->mcp($set) : null,
                'skill' => $set->config->setting('agents.skill_md', true) ? $this->urls->skill($set) : null,
            ],
            'features' => [
                'search' => (string) $set->config->setting('search.driver', 'client'),
                'ask' => false,
                'feedback' => false,
            ],
            'inserts' => $set->manifest->inserts(),
            'user' => $this->larecipe->sharedUser($this->access->user()),
            'build' => $this->assets->build(),
        ];

        foreach ($this->extenders as $extender) {
            $payload = array_replace_recursive($payload, $extender($set, $payload));
        }

        return $payload;
    }

    /**
     * The JSON a client-side navigation fetches: the article and what changes
     * around it. Navigation is included so a version or locale switch can redraw it.
     */
    public function json(array $payload, RenderedPage $rendered): array
    {
        return [
            'page' => $payload['page'] + ['html' => $rendered->html],
            'navigation' => $payload['navigation'],
            'version' => $payload['version'],
            'locale' => $payload['locale'],
            'set' => $payload['set'],
            'urls' => $payload['urls'],
            'build' => $payload['build'],
        ];
    }

    private function set(DocSet $set, PageRef $ref): array
    {
        $manifest = $set->manifest;

        return [
            'key' => $set->key,
            'name' => $manifest->name,
            'description' => $manifest->description,
            'base_path' => $set->config->basePath(),
            'logo' => $manifest->logo(),
            'favicon' => $manifest->favicon(),
            'theme' => ['mode' => $manifest->theme->mode],
            'navbar' => (array) $manifest->get('navbar', []),
            'repository' => $manifest->repository()['url'],
            'versions' => array_map(fn ($version) => $version->toArray() + [
                'url' => $this->switchUrl($set, $ref->withVersion($version->id)),
            ], $set->isVersioned() ? $set->versions() : []),
            'locales' => array_map(fn ($locale) => $locale->toArray() + [
                'url' => $this->switchUrl($set, $ref->withLocale($locale->code)),
            ], $set->isMultilingual() ? $set->locales() : []),
            'integrations' => (array) $manifest->get('integrations', []),
        ];
    }

    /** The same page in another version or locale, or that version's landing page when it does not exist there. */
    private function switchUrl(DocSet $set, PageRef $ref): string
    {
        $pages = $this->catalog->summaries($set, $ref->version, $ref->locale);

        return isset($pages[$ref->path])
            ? $this->urls->pagePath($set, $ref)
            : $this->urls->pagePath($set, $ref->withPath($set->landingPath()));
    }

    private function navigation(DocSet $set, PageRef $ref, array $visible): array
    {
        $tabs = $this->catalog->navigation($set, $ref->version, $ref->locale, $visible);

        $withUrls = function (array $items) use (&$withUrls, $set, $ref): array {
            return array_map(function (array $item) use (&$withUrls, $set, $ref) {
                if ($item['type'] === 'page') {
                    $item['url'] = $this->urls->pagePath($set, $ref->withPath($item['path']));
                    $item['active'] = $item['path'] === $ref->path;
                } elseif ($item['type'] === 'group') {
                    $item['items'] = $withUrls($item['items']);
                }

                return $item;
            }, $items);
        };

        return array_map(fn (array $tab) => ['items' => $withUrls($tab['items'])] + $tab, $tabs);
    }

    private function neighbour(DocSet $set, PageRef $ref, string $path, array $visible): ?array
    {
        $summary = $visible[$path] ?? null;

        return $summary === null ? null : [
            'title' => $summary->title,
            'url' => $this->urls->pagePath($set, $ref->withPath($path)),
        ];
    }

    private function editUrl(DocSet $set, PageRef $ref): ?string
    {
        $repository = $set->manifest->repository();
        $source = $this->sources->for($set->key);

        if ($repository['url'] === null || ! $source instanceof RepositorySource) {
            return null;
        }

        $file = $source->pageFile($ref);
        $root = realpath($source->root());

        if ($file === null || $root === false) {
            return null;
        }

        $relative = ltrim(substr((string) realpath($file), strlen($root)), '/');

        return "{$repository['url']}/edit/{$repository['branch']}/{$repository['docsPath']}/{$relative}";
    }
}
