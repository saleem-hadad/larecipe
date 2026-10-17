<?php

namespace LaRecipe\Domains\Markdown\Parser;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Recognizes a PascalCase component tag on its own line: `<Callout type="note">`
 * opens a container, `<Video src="..." />` is self-closing.
 *
 * Registered at a higher priority than CommonMark's HTML block parser, so
 * component tags are recognized without enabling raw HTML. Lowercase tags are
 * left alone and stay escaped.
 */
final class ComponentStartParser implements BlockStartParserInterface
{
    private const PATTERN = '/^<([A-Z][A-Za-z0-9]*)((?:\s+[^>]*?)?)\s*(\/?)>\s*$/s';

    public function __construct(private readonly PropsParser $props = new PropsParser) {}

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== '<') {
            return BlockStart::none();
        }

        $line = trim($cursor->getRemainder());

        if (! preg_match(self::PATTERN, $line, $matches)) {
            return BlockStart::none();
        }

        $selfClosing = $matches[3] === '/';
        $attributes = $matches[2];

        if (! $selfClosing && str_ends_with(rtrim($attributes), '/')) {
            $selfClosing = true;
            $attributes = rtrim(rtrim($attributes), '/');
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new ComponentBlockParser($matches[1], $this->props->parse($attributes), $selfClosing))->at($cursor);
    }
}
