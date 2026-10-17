<?php

namespace LaRecipe\Domains\Agents\Laravel;

use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Delivery\Services\PageCatalog;
use LaRecipe\Domains\Markdown\Services\AgentMarkdownRenderer;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;

/**
 * Reads doc sets for agent surfaces through the same catalog, access policy and
 * Markdown renderer the page controller uses, so an agent never sees a page a
 * reader could not, and the Markdown it gets is byte for byte the .md twin.
 */
final class LaravelAgentDocs implements AgentDocs
{
    public function __construct(
        private readonly SetRegistry $sets,
        private readonly PageCatalog $catalog,
        private readonly PageAccess $access,
        private readonly GetPageAction $pages,
        private readonly AgentMarkdownRenderer $renderer,
        private readonly SourceResolver $sources,
        private readonly DocsLinks $links,
    ) {}

    public function sets(): array
    {
        return array_values(array_filter($this->sets->all(), fn (DocSet $set) => $this->access->canEnter($set)));
    }

    public function set(string $key): ?DocSet
    {
        if (! $this->sets->has($key)) {
            return null;
        }

        $set = $this->sets->get($key);

        return $this->access->canEnter($set) ? $set : null;
    }

    public function defaultSet(): DocSet
    {
        return $this->sets->default();
    }

    public function canViewVersion(DocSet $set, string $version): bool
    {
        return $this->access->canViewVersion($set, $version);
    }

    public function pages(DocSet $set, string $version, string $locale): array
    {
        if (! $this->canViewVersion($set, $version)) {
            return [];
        }

        return $this->access->visible($set, $this->catalog->summaries($set, $version, $locale));
    }

    public function navigation(DocSet $set, string $version, string $locale): array
    {
        return $this->catalog->navigation($set, $version, $locale, $this->pages($set, $version, $locale));
    }

    public function page(DocSet $set, PageRef $ref): ?Page
    {
        $summary = $this->pages($set, $ref->version, $ref->locale)[$ref->path] ?? null;

        return $summary === null ? null : $this->pages->execute($summary->ref);
    }

    public function markdown(DocSet $set, Page $page, bool $hints = true): string
    {
        $versioned = $set->isVersioned();

        return $this->renderer->render($page, [
            'url' => $this->links->page($set, $page->ref),
            'version' => $versioned ? $page->ref->version : null,
            'locale' => $set->isMultilingual() ? $page->ref->locale : null,
            'llms' => $hints && $set->config->setting('agents.llms_txt', true) ? $this->links->llms($set, $versioned ? $page->ref->version : null) : null,
            'mcp' => $hints ? $this->links->mcp($set) : null,
            'skill' => $hints && $set->config->setting('agents.skill_md', true) ? $this->links->skill($set) : null,
        ]);
    }

    public function fingerprint(DocSet $set, string $version): string
    {
        return $this->catalog->fingerprint($set, $version);
    }

    public function isSharedView(DocSet $set): bool
    {
        return $this->access->isPublic($set) && $this->access->user() === null;
    }

    public function skillsDirectory(DocSet $set): ?string
    {
        $source = $this->sources->for($set->key);

        if (! $source instanceof RepositorySource) {
            return null;
        }

        $directory = $source->root().'/skills';

        return is_dir($directory) ? $directory : null;
    }
}
