<?php

namespace LaRecipe\Domains\Markdown\Services;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Markdown\Contracts\LinkResolver;
use LaRecipe\Domains\Markdown\DTOs\TocItem;

/**
 * What the parser listeners and renderers need to know about the page being
 * rendered right now, and what they learn along the way. Reset before every
 * render, so one environment can be reused safely (Octane included).
 */
final class RenderState
{
    public ?PageRef $ref = null;

    public ?LinkResolver $links = null;

    /** @var array<string, mixed> anything extensions need (abilities, the viewer, flags) */
    public array $context = [];

    /** @var list<TocItem> */
    public array $toc = [];

    public ?string $heading = null;

    /** @var array<string, true> */
    public array $components = [];

    /** @var list<array{href: string, line: ?int}> */
    public array $internalLinks = [];

    /** @var list<array{component: string, message: string}> */
    public array $issues = [];

    public bool $hasLiveReferences = false;

    /** @var array<string, true> abilities that <Can> blocks on the page asked about */
    public array $abilities = [];

    public function reset(?PageRef $ref, ?LinkResolver $links, array $context = []): void
    {
        $this->ref = $ref;
        $this->links = $links;
        $this->context = $context;
        $this->toc = [];
        $this->heading = null;
        $this->components = [];
        $this->internalLinks = [];
        $this->issues = [];
        $this->hasLiveReferences = false;
        $this->abilities = [];
    }

    public function usedComponent(string $name): void
    {
        $this->components[$name] = true;
    }

    public function componentIssue(string $component, string $message): void
    {
        $this->issues[] = ['component' => $component, 'message' => $message];
    }
}
