<?php

namespace LaRecipe\Domains\Content\DTOs;

/**
 * What navigation, llms.txt, search and list_pages need to know about a page,
 * without its body.
 */
final readonly class PageSummary
{
    public function __construct(
        public PageRef $ref,
        public string $title,
        public ?string $description = null,
        public ?int $order = null,
        public ?string $icon = null,
        public bool $hidden = false,
        public ?array $badge = null,
        public string $contentHash = '',
        public ?\DateTimeImmutable $updatedAt = null,
        public bool $private = false,
    ) {}

    public static function fromPage(Page $page): self
    {
        $badge = $page->frontmatter->get('badge');

        return new self(
            ref: $page->ref,
            title: $page->title,
            description: $page->description,
            order: $page->frontmatter->order(),
            icon: $page->frontmatter->icon(),
            hidden: $page->frontmatter->hidden(),
            badge: is_array($badge) ? $badge : (is_string($badge) ? ['label' => $badge] : null),
            contentHash: $page->contentHash,
            updatedAt: $page->updatedAt,
            private: $page->frontmatter->visibility()->value === 'private',
        );
    }

    public function toArray(): array
    {
        return [
            'path' => $this->ref->path,
            'title' => $this->title,
            'description' => $this->description,
            'order' => $this->order,
            'icon' => $this->icon,
            'hidden' => $this->hidden,
            'badge' => $this->badge,
            'updated_at' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }
}
