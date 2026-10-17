<?php

namespace LaRecipe\Domains\Access\Services;

use LaRecipe\Domains\Access\Contracts\Authorizer;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Content\Enums\Visibility;

/**
 * The one access policy for every surface: HTML, Markdown, JSON, llms.txt,
 * skill.md, sitemap, search, MCP and Ask AI all ask here (the Same-Rules Rule).
 *
 * A set's access.mode decides who may enter at all: public, auth (any signed-in
 * reader) or gate (the viewLarecipe gate). Private versions and pages always
 * need the gate. A hidden page is a 404 to the caller, never a 403.
 */
final class PageAccess
{
    public const VIEW = 'viewLarecipe';

    public const MANAGE = 'manageLarecipe';

    public const ASK = 'askLarecipe';

    public function __construct(private readonly Authorizer $authorizer) {}

    public function canEnter(DocSet $set): bool
    {
        return match ($set->config->accessMode) {
            'auth' => $this->authorizer->user() !== null,
            'gate' => $this->authorizer->allows(self::VIEW, [null, $set->key]),
            default => true,
        };
    }

    public function canViewVersion(DocSet $set, string $version): bool
    {
        if (! $this->canEnter($set)) {
            return false;
        }

        $declared = $set->version($version);

        if ($declared === null) {
            return false;
        }

        return $declared->visibility !== Visibility::Private || $this->authorizer->allows(self::VIEW, [null, $set->key]);
    }

    public function canView(DocSet $set, PageSummary $page): bool
    {
        if (! $this->canViewVersion($set, $page->ref->version)) {
            return false;
        }

        if ($page->private || $set->config->accessMode === 'gate') {
            return $this->authorizer->allows(self::VIEW, [$page, $set->key]);
        }

        return true;
    }

    /**
     * @param  array<string, PageSummary>  $pages
     * @return array<string, PageSummary>
     */
    public function visible(DocSet $set, array $pages): array
    {
        return array_filter($pages, fn (PageSummary $page) => $this->canView($set, $page));
    }

    public function allows(string $ability, array $arguments = []): bool
    {
        return $this->authorizer->allows($ability, $arguments);
    }

    public function canManage(DocSet $set): bool
    {
        return $this->authorizer->allows(self::MANAGE, [$set->key]);
    }

    /** True when every reader sees the same thing, so responses may be cached publicly. */
    public function isPublic(DocSet $set): bool
    {
        return $set->config->accessMode === 'public';
    }

    /**
     * True when a guest may read this page: a public set, a public version and a
     * public page. Anything else is personal content that shared caches must not keep.
     */
    public function isPublicPage(DocSet $set, PageSummary $page): bool
    {
        return $this->isPublic($set)
            && ! $page->private
            && $set->version($page->ref->version)?->visibility !== Visibility::Private;
    }

    public function user(): ?object
    {
        return $this->authorizer->user();
    }
}
