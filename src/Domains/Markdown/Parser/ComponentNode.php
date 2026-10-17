<?php

namespace LaRecipe\Domains\Markdown\Parser;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A component in the Markdown AST. `<Callout type="warning">` and
 * `:::callout{type=warning}` both become this node, so every renderer
 * (HTML, agent Markdown, search records) sees one shape.
 */
final class ComponentNode extends AbstractBlock
{
    /** @param array<string, mixed> $props */
    public function __construct(
        public readonly string $name,
        public array $props = [],
        public readonly bool $selfClosing = false,
        public readonly string $syntax = 'tag',
    ) {
        parent::__construct();
    }
}
