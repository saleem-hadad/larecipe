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
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public ?PageRef $ref = null;

    public ?LinkResolver $links = null;

    /**
     * Anything extensions need (abilities, the viewer, flags). Known keys:
     * snippets_root overrides the doc set's snippet root for this render.
     *
     * @var array<string, mixed>
     */
    public array $context = [];

    /** @var list<TocItem> */
    public array $toc = [];

    public ?string $heading = null;

    /** @var array<string, true> */
    public array $components = [];

    /** @var list<array{href: string, line: ?int}> */
    public array $internalLinks = [];

    /** @var list<array{rule: string, level: string, message: string, component?: string, line?: int}> */
    public array $issues = [];

    public bool $hasLiveReferences = false;

    /** Raw HTML passes as written (a repository set with html_input: allow); escaped otherwise. */
    public bool $rawHtml = false;

    /** @var array<string, true> abilities that <Can> blocks on the page asked about */
    public array $abilities = [];

    /**
     * Files outside the page that the render read (snippet includes), with a
     * hash of their contents. A cached render is stale when one of them changes.
     *
     * @var array<string, string>
     */
    public array $dependencies = [];

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
        $this->rawHtml = false;
        $this->abilities = [];
        $this->dependencies = [];
    }

    public function usedComponent(string $name): void
    {
        $this->components[$name] = true;
    }

    /** A problem found while rendering, with a stable rule id validation and agents can act on. */
    public function issue(string $rule, string $message, string $level = self::WARNING, ?string $component = null, ?int $line = null): void
    {
        $this->issues[] = array_filter([
            'rule' => $rule,
            'level' => $level,
            'message' => $message,
            'component' => $component,
            'line' => $line,
        ], fn ($value) => $value !== null);
    }

    public function componentIssue(string $component, string $message): void
    {
        $this->issue('components.invalid', $message, self::ERROR, $component);
    }

    public function dependsOn(string $file, string $hash): void
    {
        $this->dependencies[$file] = $hash;
    }
}
