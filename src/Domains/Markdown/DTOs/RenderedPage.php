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
     * @param  list<array{component: string, message: string}>  $issues  render-time problems, for validation
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
    ) {}

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
        );
    }
}
