<?php

namespace LaRecipe\Domains\Markdown\Parser;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Display math between `$$` lines, the way GitHub and most Markdown tools write
 * it. It becomes a Math component, so `$$ ... $$` and `<Math>` render the same.
 *
 *   $$                      $$ e^{i\pi} + 1 = 0 $$
 *   e^{i\pi} + 1 = 0
 *   $$
 */
final class MathStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== '$') {
            return BlockStart::none();
        }

        $line = trim($cursor->getRemainder());

        if (! str_starts_with($line, '$$')) {
            return BlockStart::none();
        }

        $rest = trim(substr($line, 2));
        $cursor->advanceToEnd();
        $block = new ComponentNode('Math', [], syntax: 'math');

        if (strlen($rest) >= 2 && str_ends_with($rest, '$$')) {
            return BlockStart::of(new RawComponentParser($block, null, trim(substr($rest, 0, -2))))->at($cursor);
        }

        return BlockStart::of(new RawComponentParser($block, function (string $line): false|string {
            $line = trim($line);

            return str_ends_with($line, '$$') ? trim(substr($line, 0, -2)) : false;
        }, $rest))->at($cursor);
    }
}
