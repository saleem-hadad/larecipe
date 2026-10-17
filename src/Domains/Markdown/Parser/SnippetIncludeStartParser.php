<?php

namespace LaRecipe\Domains\Markdown\Parser;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Recognizes a snippet include on its own line (the Real-Code Rule):
 *
 *   <<< @/app/Billing/Invoice.php                    the whole file
 *   <<< @/app/Billing/Invoice.php#refund             one region
 *   <<< @/app/Billing/Invoice.php#refund {3} title="Refunds"
 *
 * `@/` is the root configured for the doc set. Anything after the target is
 * code block meta (line highlights, a title, showLineNumbers).
 */
final class SnippetIncludeStartParser implements BlockStartParserInterface
{
    private const PATTERN = '/^<<<\s+@\/([^\s#{]+)(?:#([A-Za-z0-9_.-]+))?\s*(.*)$/';

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== '<') {
            return BlockStart::none();
        }

        if (! preg_match(self::PATTERN, trim($cursor->getRemainder()), $matches)) {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();
        $include = new SnippetInclude($matches[1], $matches[2] !== '' ? $matches[2] : null, trim($matches[3]));

        return BlockStart::of(new SnippetIncludeParser($include))->at($cursor);
    }
}
