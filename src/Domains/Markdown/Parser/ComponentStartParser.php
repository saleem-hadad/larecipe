<?php

namespace LaRecipe\Domains\Markdown\Parser;

use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Recognizes a PascalCase component tag on its own line:
 *
 *   <Callout type="note">            opens a container, closed by </Callout>
 *   <Video src="..." />              is self-closing
 *   <Badge tone="info">New</Badge>   carries inline content on one line
 *
 * Raw components (Mermaid, Math) keep everything up to their closing tag as
 * literal text. Registered at a higher priority than CommonMark's HTML block
 * parser, so component tags are recognized without enabling raw HTML.
 * Lowercase tags are left alone and stay escaped.
 */
final class ComponentStartParser implements BlockStartParserInterface
{
    private const OPEN = '/^<([A-Z][A-Za-z0-9]*)((?:\s+[^>]*?)?)\s*(\/?)>\s*$/s';

    private const ONE_LINE = '/^<([A-Z][A-Za-z0-9]*)((?:\s+[^>]*?)?)>(.*)<\/\1>\s*$/s';

    public function __construct(
        private readonly ?ComponentRegistry $registry = null,
        private readonly PropsParser $props = new PropsParser,
    ) {}

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== '<') {
            return BlockStart::none();
        }

        $line = trim($cursor->getRemainder());

        if (preg_match(self::ONE_LINE, $line, $matches)) {
            $cursor->advanceToEnd();

            return BlockStart::of($this->oneLine($matches[1], $this->props->parse($matches[2]), $matches[3]))->at($cursor);
        }

        if (! preg_match(self::OPEN, $line, $matches)) {
            return BlockStart::none();
        }

        $name = $matches[1];
        $selfClosing = $matches[3] === '/';
        $attributes = $matches[2];

        if (! $selfClosing && str_ends_with(rtrim($attributes), '/')) {
            $selfClosing = true;
            $attributes = rtrim(rtrim($attributes), '/');
        }

        $cursor->advanceToEnd();
        $props = $this->props->parse($attributes);

        if (! $selfClosing && $this->isRaw($name)) {
            $closing = '</'.$name.'>';

            return BlockStart::of(new RawComponentParser(
                new ComponentNode($name, $props),
                function (string $line) use ($closing): false|string {
                    $line = trim($line);

                    return str_ends_with($line, $closing) ? trim(substr($line, 0, -strlen($closing))) : false;
                },
            ))->at($cursor);
        }

        return BlockStart::of(new ComponentBlockParser($name, $props, $selfClosing))->at($cursor);
    }

    private function oneLine(string $name, array $props, string $content): RawComponentParser|InlineComponentParser
    {
        if ($this->isRaw($name)) {
            return new RawComponentParser(new ComponentNode($name, $props, inline: true), null, trim($content));
        }

        return new InlineComponentParser(new ComponentNode($name, $props, inline: true), $content);
    }

    private function isRaw(string $name): bool
    {
        return $this->registry?->get($name)?->raw ?? false;
    }
}
