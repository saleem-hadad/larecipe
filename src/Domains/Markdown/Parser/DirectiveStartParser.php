<?php

namespace LaRecipe\Domains\Markdown\Parser;

use LaRecipe\Domains\Markdown\Components\BuiltIn\Callout;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Recognizes container directives, the other way to write a component:
 *
 *   :::warning Read this first          a Callout with a title
 *   :::tabs {sync=pm}                   any component, props in braces
 *   ::::steps                           longer fences nest shorter ones
 *   :::
 *
 * A directive produces the same ComponentNode as the matching tag, so the
 * renderers never know which syntax the author chose. Callout types
 * (`:::note`, `:::tip`, `:::warning`, ...) are shorthand for a Callout.
 */
final class DirectiveStartParser implements BlockStartParserInterface
{
    private const OPEN = '/^(:{3,})\s*([A-Za-z][\w-]*)\s*(.*?)\s*$/';

    public function __construct(
        private readonly ComponentRegistry $registry,
        private readonly PropsParser $props = new PropsParser,
    ) {}

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== ':') {
            return BlockStart::none();
        }

        if (! preg_match(self::OPEN, trim($cursor->getRemainder()), $matches)) {
            return BlockStart::none();
        }

        $fence = strlen($matches[1]);
        [$name, $props] = $this->component($matches[2]);
        [$title, $attributes] = $this->split($matches[3]);
        $props = [...$props, ...$this->props->parse($attributes)];

        if ($title !== '') {
            $props[$this->titleProp($name)] ??= $title;
        }

        $cursor->advanceToEnd();

        if ($this->registry->get($name)?->raw ?? false) {
            $block = new ComponentNode($name, $props, syntax: 'directive');

            return BlockStart::of(new RawComponentParser($block, fn (string $line) => self::closingFence($line, $fence) ? '' : false))->at($cursor);
        }

        return BlockStart::of(new DirectiveBlockParser($name, $props, $fence))->at($cursor);
    }

    /** The length of a closing fence (`:::`), or null when the line is not one. */
    public static function closingFence(string $line, int $atLeast = 3): ?int
    {
        if (preg_match('/^\s*(:{3,})\s*$/', $line, $matches) && strlen($matches[1]) >= $atLeast) {
            return strlen($matches[1]);
        }

        return null;
    }

    /** @return array{0: string, 1: array<string, mixed>} the component name and implied props */
    private function component(string $directive): array
    {
        $lower = strtolower($directive);

        if (in_array($lower, Callout::TYPES, true)) {
            return ['Callout', ['type' => $lower]];
        }

        $pascal = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $lower)));

        foreach (array_keys($this->registry->all()) as $name) {
            if (strcasecmp($name, $pascal) === 0) {
                return [$name, []];
            }
        }

        return [$pascal, []];
    }

    /** @return array{0: string, 1: string} the title text and the attributes inside braces */
    private function split(string $rest): array
    {
        if (preg_match('/^(.*?)\s*\{(.*)\}$/s', $rest, $matches)) {
            return [trim($matches[1]), $matches[2]];
        }

        return [trim($rest), ''];
    }

    /** The prop a directive's title fills: title for most components, label for a Tab. */
    private function titleProp(string $name): string
    {
        $props = $this->registry->get($name)?->props ?? [];

        return ! isset($props['title']) && isset($props['label']) ? 'label' : 'title';
    }
}
