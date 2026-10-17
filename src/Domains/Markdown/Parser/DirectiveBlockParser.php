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
 * Keeps a `:::name` directive open until a closing fence at least as long as
 * its opening fence. A closing fence belongs to the innermost open directive it
 * can close, so `::::tabs` around `:::tab` blocks (or equal fences, closed in
 * order) nest the way authors expect.
 */
final class DirectiveBlockParser extends AbstractBlockContinueParser
{
    public const FENCE = 'larecipe_directive_fence';

    private readonly ComponentNode $block;

    public function __construct(string $name, array $props, private readonly int $fence)
    {
        $this->block = new ComponentNode($name, $props, syntax: 'directive');
        $this->block->data->set(self::FENCE, $fence);
    }

    public function getBlock(): AbstractBlock
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        $active = $activeBlockParser->getBlock();
        $insideCode = $active instanceof FencedCode || $active instanceof IndentedCode || $active instanceof HtmlBlock
            || $activeBlockParser instanceof RawComponentParser;

        $length = $insideCode || $cursor->isIndented() ? null : DirectiveStartParser::closingFence($cursor->getRemainder(), $this->fence);

        if ($length !== null && ! $this->innerDirectiveCanClose($active, $length)) {
            $cursor->advanceToEnd();

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    /** Whether a directive opened inside this one can take a closing fence of this length. */
    private function innerDirectiveCanClose(AbstractBlock $deepest, int $length): bool
    {
        for ($node = $deepest; $node !== null && $node !== $this->block; $node = $node->parent()) {
            if ($node instanceof ComponentNode && $node->syntax === 'directive' && (int) $node->data->get(self::FENCE, 3) <= $length) {
                return true;
            }
        }

        return false;
    }
}
