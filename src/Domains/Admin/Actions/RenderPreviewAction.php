<?php

namespace LaRecipe\Domains\Admin\Actions;

use LaRecipe\Domains\Access\DTOs\Audience;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Delivery\Support\DocsLinkResolver;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Markdown\DTOs\RenderedPage;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;

/**
 * Renders a page the way a reader gets it: the same renderer, the same link
 * resolver and the guest audience the page controller uses for its shared
 * render. The editor preview, the review queue's rendered diff and the
 * revision history all use it, so what a reviewer signs off is exactly what
 * readers will see.
 */
final class RenderPreviewAction
{
    public function __construct(
        private readonly RenderPageAction $render,
        private readonly DocsUrls $urls,
        private readonly SourceResolver $sources,
    ) {}

    public function execute(DocSet $set, Page $page): RenderedPage
    {
        return $this->render->execute(
            $page,
            new DocsLinkResolver($set, $this->urls, $this->sources->for($set->key)),
            Audience::guest()->toContext(),
        );
    }
}
