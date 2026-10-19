<?php

namespace LaRecipe\Domains\Content\DTOs;

use DateTimeImmutable;

/**
 * One page: its identity, its page-level config and its Markdown body.
 */
final readonly class Page
{
    public function __construct(
        public PageRef $ref,
        public string $title,
        public ?string $description,
        public Frontmatter $frontmatter,
        public string $body,
        public string $contentHash,
        public ?DateTimeImmutable $updatedAt = null,
        public ?Provenance $provenance = null,
    ) {}

    /**
     * Build a page from its raw parts, deriving the title from the first H1
     * when the frontmatter does not set one.
     */
    public static function make(
        PageRef $ref,
        Frontmatter $frontmatter,
        string $body,
        ?DateTimeImmutable $updatedAt = null,
        ?Provenance $provenance = null,
    ): self {
        return new self(
            ref: $ref,
            title: $frontmatter->title() ?? self::firstHeading($body) ?? self::humanize($ref->path),
            description: $frontmatter->description(),
            frontmatter: $frontmatter,
            body: $body,
            contentHash: self::hash($frontmatter, $body),
            updatedAt: $updatedAt,
            provenance: $provenance,
        );
    }

    public static function hash(Frontmatter $frontmatter, string $body): string
    {
        return 'sha256:'.hash('sha256', json_encode($frontmatter->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n".$body);
    }

    /** @return list<string> globs of code this page documents */
    public function sources(): array
    {
        return $this->frontmatter->sources();
    }

    public function withProvenance(?Provenance $provenance, ?DateTimeImmutable $updatedAt = null): self
    {
        return new self($this->ref, $this->title, $this->description, $this->frontmatter, $this->body, $this->contentHash, $updatedAt ?? $this->updatedAt, $provenance);
    }

    public static function fromArray(array $data): self
    {
        $ref = new PageRef($data['ref']['set'], $data['ref']['version'], $data['ref']['locale'], $data['ref']['path']);

        return self::make($ref, new Frontmatter($data['frontmatter'] ?? []), (string) ($data['body'] ?? ''));
    }

    public function toArray(): array
    {
        return [
            'ref' => $this->ref->toArray(),
            'title' => $this->title,
            'description' => $this->description,
            'frontmatter' => $this->frontmatter->toArray(),
            'body' => $this->body,
            'content_hash' => $this->contentHash,
            'updated_at' => $this->updatedAt?->format(DATE_ATOM),
            'provenance' => $this->provenance?->toArray(),
        ];
    }

    private static function firstHeading(string $body): ?string
    {
        if (preg_match('/^#\s+(.+?)\s*#*\s*$/m', $body, $matches)) {
            return trim(preg_replace('/\s*\{[#.][^}]*\}\s*$/', '', $matches[1]) ?? $matches[1]);
        }

        return null;
    }

    private static function humanize(string $path): string
    {
        $last = basename($path === 'index' ? 'introduction' : $path);

        return ucfirst(str_replace(['-', '_'], ' ', $last));
    }
}
