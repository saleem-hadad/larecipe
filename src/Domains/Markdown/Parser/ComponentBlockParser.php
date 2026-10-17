<?php

namespace LaRecipe\Domains\Markdown\Parser;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * Keeps a component open until its closing tag (`</Name>`) on its own line.
 * Everything in between is parsed as regular Markdown, nested components included.
 *
 * Open blocks are asked to continue from the outside in, so an outer `<Tabs>`
 * sees an inner `</Tabs>` first. It lets the closing tag through when a
 * component with the same name is open inside it, which makes same-name
 * nesting work.
 */
final class ComponentBlockParser extends AbstractBlockContinueParser
{
    private readonly ComponentNode $block;

    public function __construct(string $name, array $props, private readonly bool $selfClosing)
    {
        $this->block = new ComponentNode($name, $props, $selfClosing);
    }

    public function getBlock(): AbstractBlock
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return ! $this->selfClosing;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return ! $this->selfClosing;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($this->selfClosing) {
            return BlockContinue::none();
        }

        $active = $activeBlockParser->getBlock();
        $insideCode = $active instanceof FencedCode || $active instanceof IndentedCode || $active instanceof HtmlBlock
            || $activeBlockParser instanceof RawComponentParser;

        if (! $insideCode && ! $cursor->isIndented() && trim($cursor->getRemainder()) === '</'.$this->block->name.'>' && ! $this->innerSameNameIsOpen($active)) {
            $cursor->advanceToEnd();

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    /** Whether a component with this name is open between the deepest open block and this one. */
    private function innerSameNameIsOpen(AbstractBlock $deepest): bool
    {
        for ($node = $deepest; $node !== null && $node !== $this->block; $node = $node->parent()) {
            if ($node instanceof ComponentNode && $node->name === $this->block->name && ! $node->selfClosing && $node->syntax === 'tag') {
                return true;
            }
        }

        return false;
    }
}
