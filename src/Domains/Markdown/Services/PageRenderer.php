<?php

namespace LaRecipe\Domains\Markdown\Services;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Markdown\Contracts\LinkResolver;
use LaRecipe\Domains\Markdown\DTOs\RenderedPage;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

/**
 * Parses a page once and renders its article HTML.
 *
 * The parsed document is the contract: HTML, agent Markdown, search records and
 * validation data are all projections of the same AST.
 */
final class PageRenderer
{
    private ?Environment $environment = null;

    private readonly RenderState $state;

    public function __construct(private readonly MarkdownEnvironmentFactory $factory)
    {
        $this->state = new RenderState;
    }

    public function parse(Page $page, ?LinkResolver $links = null, array $context = []): Document
    {
        $this->state->reset($page->ref, $links, $context);

        return (new MarkdownParser($this->environment()))->parse($page->body);
    }

    public function render(Page $page, ?LinkResolver $links = null, array $context = []): RenderedPage
    {
        $document = $this->parse($page, $links, $context);

        // The page header shows the title, so a leading "# Title" is not repeated in the article.
        $first = $document->firstChild();

        if ($first instanceof Heading && $first->getLevel() === 1) {
            $first->detach();
        }

        $html = (string) (new HtmlRenderer($this->environment()))->renderDocument($document);

        return new RenderedPage(
            html: $html,
            toc: $this->state->toc,
            heading: $this->state->heading,
            components: array_keys($this->state->components),
            links: $this->state->internalLinks,
            issues: $this->state->issues,
            hasLiveReferences: $this->state->hasLiveReferences,
            abilities: array_keys($this->state->abilities),
            dependencies: $this->state->dependencies,
        );
    }

    public function state(): RenderState
    {
        return $this->state;
    }

    public function environment(): Environment
    {
        return $this->environment ??= $this->factory->make($this->state);
    }
}
