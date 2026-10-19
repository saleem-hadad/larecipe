<?php

namespace LaRecipe\Domains\Markdown\Parser;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockContinueParserWithInlinesInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\InlineParserEngineInterface;

/**
 * A component written on one line with its content (`<Badge tone="success">Stable</Badge>`).
 * The content is parsed as inline Markdown, so `**bold**` and links work inside.
 */
final class InlineComponentParser extends AbstractBlockContinueParser implements BlockContinueParserWithInlinesInterface
{
    public function __construct(
        private readonly ComponentNode $block,
        private readonly string $content,
    ) {}

    public function getBlock(): AbstractBlock
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        return BlockContinue::none();
    }

    public function parseInlines(InlineParserEngineInterface $inlineParser): void
    {
        $inlineParser->parse(trim($this->content), $this->block);
    }
}
