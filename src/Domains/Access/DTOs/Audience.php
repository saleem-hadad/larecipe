<?php

namespace LaRecipe\Domains\Access\DTOs;

/**
 * Who a page is rendered for, as far as gate blocks are concerned: which of the
 * abilities the page asks about the reader holds.
 *
 * Rendered pages are cached per audience, never per user. Two readers who hold
 * the same abilities on a page share one cached render, and a reader who holds
 * none of them shares the guest render. The audience travels to the Markdown
 * environment in RenderState::$context under CONTEXT_KEY; a render without one
 * is treated as a guest, so a renderer that forgets to pass it can only ever
 * show less, never more.
 */
final readonly class Audience
{
    public const CONTEXT_KEY = 'audience';

    /**
     * Render purposes that must see every gate block: search record extraction
     * splits records at <Can>/<Cannot> nodes and filters them per audience itself.
     */
    public const PURPOSES_SEEING_EVERY_BLOCK = ['search'];

    /**
     * @param  array<string, bool>  $abilities  ability => granted, for the abilities that were evaluated
     * @param  bool  $unrestricted  keep every gate block (validation and authoring previews only, never readers)
     */
    public function __construct(
        public array $abilities = [],
        public bool $unrestricted = false,
    ) {}

    /** A reader who holds no ability: signed-out readers, crawlers, static exports. */
    public static function guest(): self
    {
        return new self;
    }

    /**
     * Keeps every <Can> and <Cannot> block in the tree, so validation and search
     * record extraction can see (and tag) all of them. Never serve this to a reader.
     */
    public static function unrestricted(): self
    {
        return new self([], true);
    }

    /** @param list<string> $granted */
    public static function granting(array $granted): self
    {
        return new self(array_fill_keys($granted, true));
    }

    /**
     * The audience a render was asked for, or a guest when none was given (fail
     * closed). A search parse (context purpose "search") keeps every block.
     */
    public static function fromContext(array $context): self
    {
        if (in_array($context['purpose'] ?? null, self::PURPOSES_SEEING_EVERY_BLOCK, true)) {
            return self::unrestricted();
        }

        $audience = $context[self::CONTEXT_KEY] ?? null;

        return $audience instanceof self ? $audience : self::guest();
    }

    public function allows(string $ability): bool
    {
        return $this->unrestricted || ($this->abilities[$ability] ?? false) === true;
    }

    /** @return list<string> the granted abilities, sorted, so equal audiences share one key */
    public function granted(): array
    {
        $granted = array_keys(array_filter($this->abilities, fn (bool $granted) => $granted));
        sort($granted);

        return $granted;
    }

    /** True when the reader sees exactly what a guest sees. */
    public function isGuest(): bool
    {
        return ! $this->unrestricted && $this->granted() === [];
    }

    /** A short, stable cache key: "guest", "all", or a hash of the granted abilities. */
    public function key(): string
    {
        return match (true) {
            $this->unrestricted => 'all',
            $this->isGuest() => 'guest',
            default => substr(hash('xxh128', implode("\n", $this->granted())), 0, 16),
        };
    }

    /** @return array<string, mixed> the render context entry for this audience */
    public function toContext(): array
    {
        return [self::CONTEXT_KEY => $this];
    }
}
