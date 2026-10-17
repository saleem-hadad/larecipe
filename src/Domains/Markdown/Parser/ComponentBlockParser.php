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
        $insideCode = $active instanceof FencedCode || $active instanceof IndentedCode || $active instanceof HtmlBlock;

        if (! $insideCode && ! $cursor->isIndented() && trim($cursor->getRemainder()) === '</'.$this->block->name.'>' && ! $this->hasOpenNamesakeInside($active)) {
            $cursor->advanceToEnd();

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    /** A closing tag belongs to the innermost open component of that name (<Can> inside <Can>). */
    private function hasOpenNamesakeInside(AbstractBlock $active): bool
    {
        for ($node = $active; $node !== null && $node !== $this->block; $node = $node->parent()) {
            if ($node instanceof ComponentNode && $node->name === $this->block->name) {
                return true;
            }
        }

        return false;
    }
}
