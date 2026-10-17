<?php

namespace LaRecipe\Domains\Markdown\Parser;

use Closure;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * Collects the content of a raw component (Mermaid, Math) line by line, without
 * parsing it as Markdown, until its closing line: `</Mermaid>`, a `:::` fence
 * or `$$`.
 *
 * The closing rule is a closure that returns false for an ordinary line, or the
 * text that precedes the closing marker on the closing line ('' when none).
 */
final class RawComponentParser extends AbstractBlockContinueParser
{
    /** @var list<string> */
    private array $lines = [];

    private bool $openingLine = true;

    /**
     * @param  (Closure(string): (false|string))|null  $closes  null when the content is complete already
     */
    public function __construct(
        private readonly ComponentNode $block,
        private readonly ?Closure $closes,
        ?string $firstLine = null,
    ) {
        if ($firstLine !== null && $firstLine !== '') {
            $this->lines[] = $firstLine;
        }
    }

    public function getBlock(): AbstractBlock
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($this->closes === null) {
            return BlockContinue::none();
        }

        $before = ($this->closes)($cursor->getRemainder());

        if ($before === false) {
            return BlockContinue::at($cursor);
        }

        if ($before !== '') {
            $this->lines[] = $before;
        }

        $cursor->advanceToEnd();

        return BlockContinue::finished();
    }

    public function addLine(string $line): void
    {
        // The opening line was consumed by the start parser and arrives here empty.
        if ($this->openingLine) {
            $this->openingLine = false;

            return;
        }

        $this->lines[] = $line;
    }

    public function closeBlock(): void
    {
        $this->block->literal = rtrim(implode("\n", $this->lines), "\n");
    }
}
