<?php

namespace LaRecipe\Domains\Markdown\DTOs;

/**
 * A page after rendering: its article HTML and what was learned while rendering it.
 */
final readonly class RenderedPage
{
    /**
     * @param  list<TocItem>  $toc
     * @param  list<string>  $components  component names used on the page
     * @param  list<array{href: string, line: ?int}>  $links  internal links, for validation
     * @param  list<array{rule?: string, level?: string, message: string, component?: string, line?: int}>  $issues  render-time problems, for validation
     * @param  array<string, string>  $dependencies  files the render read (snippet includes) => content hash
     */
    public function __construct(
        public string $html,
        public array $toc,
        public ?string $heading,
        public array $components = [],
        public array $links = [],
        public array $issues = [],
        public bool $hasLiveReferences = false,
        public array $abilities = [],
        public array $dependencies = [],
    ) {}

    /** Errors make a render unfit to cache: the author has to fix the page first. */
    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if (($issue['level'] ?? 'error') === 'error') {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a file this render read (a snippet include) has changed since. The
     * page's own content hash cannot see those files, so caches check this.
     */
    public function dependenciesChanged(): bool
    {
        foreach ($this->dependencies as $file => $hash) {
            if ((is_file($file) ? hash_file('xxh128', $file) : 'missing') !== $hash) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return [
            'html' => $this->html,
            'toc' => array_map(fn (TocItem $item) => $item->toArray(), $this->toc),
            'heading' => $this->heading,
            'components' => $this->components,
            'links' => $this->links,
            'issues' => $this->issues,
            'has_live_references' => $this->hasLiveReferences,
            'abilities' => $this->abilities,
            'dependencies' => $this->dependencies,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            html: $data['html'],
            toc: array_map(fn (array $item) => new TocItem($item['level'], $item['id'], $item['text']), $data['toc']),
            heading: $data['heading'],
            components: $data['components'] ?? [],
            links: $data['links'] ?? [],
            issues: $data['issues'] ?? [],
            hasLiveReferences: $data['has_live_references'] ?? false,
            abilities: $data['abilities'] ?? [],
            dependencies: $data['dependencies'] ?? [],
        );
    }
}
