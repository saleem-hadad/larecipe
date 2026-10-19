<?php

namespace LaRecipe\Domains\Markdown\Parser;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A component in the Markdown AST. `<Callout type="warning">`,
 * `:::warning` and `> [!WARNING]` all become this node, so every renderer
 * (HTML, agent Markdown, search records) sees one shape.
 *
 * Children are block Markdown by default. A one-line tag
 * (`<Badge>Stable</Badge>`) holds inline children instead, and a raw
 * component (Mermaid, Math) holds its content as literal text.
 */
final class ComponentNode extends AbstractBlock
{
    /** The literal content of a raw component, set when its block closes. */
    public ?string $literal = null;

    /** A container tag that ended without its closing tag (the end of the page, a quote or a list ended it). */
    public bool $unclosed = false;

    /** @param array<string, mixed> $props */
    public function __construct(
        public readonly string $name,
        public array $props = [],
        public readonly bool $selfClosing = false,
        public readonly string $syntax = 'tag',
        public readonly bool $inline = false,
    ) {
        parent::__construct();
    }

    public function isRaw(): bool
    {
        return $this->literal !== null;
    }
}
