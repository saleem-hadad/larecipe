<?php

namespace LaRecipe\Domains\Markdown\Services;

use Closure;
use LaRecipe\Domains\Markdown\Components\BuiltInComponents;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Components\RenderedComponent;
use LaRecipe\Domains\Markdown\DTOs\CodeFence;
use LaRecipe\Domains\Markdown\Parser\ComponentNode;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\DescriptionList\Node\Description;
use League\CommonMark\Extension\DescriptionList\Node\DescriptionList;
use League\CommonMark\Extension\DescriptionList\Node\DescriptionTerm;
use League\CommonMark\Extension\Footnote\Node\Footnote;
use League\CommonMark\Extension\Footnote\Node\FootnoteBackref;
use League\CommonMark\Extension\Footnote\Node\FootnoteContainer;
use League\CommonMark\Extension\Footnote\Node\FootnoteRef;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Extension\TaskList\TaskListItemMarker;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Writes a parsed page back out as Markdown for agents: the same AST the HTML
 * comes from, projected to portable CommonMark and GFM.
 *
 * Components degrade through their `markdown` form, with their children
 * serialized first; a component without one (or an unknown tag) becomes its
 * children. Links and images are made absolute. Nothing is read from the raw
 * source, so the `.md` twin can never disagree with the HTML (the No-Cloaking Rule).
 */
final class MarkdownSerializer
{
    /** @var Closure(string): string */
    private Closure $url;

    private ?RenderState $state = null;

    public function __construct(private readonly ComponentRegistry $components) {}

    /** @param Closure(string): string $url makes a link or image target absolute */
    public function serialize(Document $document, Closure $url, ?RenderState $state = null): string
    {
        $this->url = $url;
        $this->state = $state;

        return trim($this->blocks($document))."\n";
    }

    private function blocks(Node $parent, string $separator = "\n\n"): string
    {
        $parts = [];

        foreach ($parent->children() as $child) {
            $text = $this->block($child);

            if (trim($text) !== '') {
                $parts[] = $text;
            }
        }

        return implode($separator, $parts);
    }

    private function block(Node $node): string
    {
        return match (true) {
            $node instanceof Heading => str_repeat('#', $node->getLevel()).' '.$this->inlines($node),
            $node instanceof Paragraph => $this->inlines($node),
            $node instanceof BlockQuote => $this->quote($this->blocks($node)),
            $node instanceof ListBlock => $this->list($node),
            $node instanceof FencedCode => $this->fence($node->getLiteral(), CodeFence::fromInfo($node->getInfo())),
            $node instanceof IndentedCode => $this->fence($node->getLiteral(), new CodeFence),
            $node instanceof ThematicBreak => '---',
            $node instanceof HtmlBlock => rtrim($node->getLiteral()),
            $node instanceof Table => $this->table($node),
            $node instanceof ComponentNode => $this->component($node),
            $node instanceof FootnoteContainer => $this->blocks($node, "\n"),
            $node instanceof Footnote => '[^'.$this->label($node->getReference()->getLabel()).']: '.ltrim($this->blocks($node, "\n\n    ")),
            $node instanceof DescriptionList => $this->blocks($node, "\n"),
            $node instanceof DescriptionTerm => $this->inlines($node),
            $node instanceof Description => ': '.$this->blocks($node),
            default => $this->blocks($node),
        };
    }

    private function component(ComponentNode $node): string
    {
        $children = $node->isRaw()
            ? (string) $node->literal
            : ($node->inline ? $this->inlines($node) : $this->blocks($node));

        $definition = $this->components->get($node->name);

        if ($definition?->markdown === null) {
            return $children;
        }

        $props = $definition->resolveProps($node->props);

        foreach (BuiltInComponents::URL_PROPS as $prop) {
            if (isset($props[$prop]) && is_string($props[$prop]) && $props[$prop] !== '') {
                $props[$prop] = ($this->url)($props[$prop]);
            }
        }

        $ref = $this->state?->ref;
        $context = new RenderedComponent($node->name, $props, $ref?->set, $ref?->version, $ref?->locale, $ref?->path);

        return trim((string) ($definition->markdown)($props, $children, $context), "\n");
    }

    private function list(ListBlock $list): string
    {
        $data = $list->getListData();
        $ordered = $data->type === ListBlock::TYPE_ORDERED;
        $number = $data->start ?? 1;
        $separator = $list->isTight() ? "\n" : "\n\n";
        $items = [];

        foreach ($list->children() as $item) {
            $marker = $ordered ? ($number++).'.' : '-';
            $body = $this->blocks($item, $separator);
            $items[] = $marker.' '.$this->hang($body, str_repeat(' ', strlen($marker) + 1));
        }

        return implode($separator, $items);
    }

    private function table(Table $table): string
    {
        $rows = [];
        $aligns = [];

        foreach ($table->children() as $section) {
            if (! $section instanceof TableSection) {
                continue;
            }

            foreach ($section->children() as $row) {
                if (! $row instanceof TableRow) {
                    continue;
                }

                $cells = [];

                foreach ($row->children() as $cell) {
                    if ($cell instanceof TableCell) {
                        $cells[] = str_replace('|', '\|', $this->inlines($cell));

                        if ($section->isHead()) {
                            $aligns[] = $cell->getAlign();
                        }
                    }
                }

                $rows[] = '| '.implode(' | ', $cells).' |';

                if ($section->isHead()) {
                    $rows[] = '| '.implode(' | ', array_map(fn (?string $align) => match ($align) {
                        'left' => ':---',
                        'center' => ':---:',
                        'right' => '---:',
                        default => '---',
                    }, $aligns)).' |';
                }
            }
        }

        return implode("\n", $rows);
    }

    private function fence(string $code, CodeFence $fence): string
    {
        preg_match_all('/`+/', $code, $runs);
        $ticks = str_repeat('`', max(3, ...array_map(fn (string $run) => strlen($run) + 1, $runs[0] ?: [''])));
        $info = trim(($fence->language ?? '')
            .($fence->title !== null ? ' title="'.str_replace('"', "'", $fence->title).'"' : '')
            .($fence->diff && $fence->language !== 'diff' ? ' diff' : ''));

        return $ticks.$info."\n".rtrim($code, "\n")."\n".$ticks;
    }

    private function inlines(Node $parent): string
    {
        $out = '';

        foreach ($parent->children() as $child) {
            $out .= match (true) {
                $child instanceof Text => $this->escape($child->getLiteral()),
                $child instanceof Code => $this->code($child->getLiteral()),
                $child instanceof Strong => '**'.$this->inlines($child).'**',
                $child instanceof Emphasis => '*'.$this->inlines($child).'*',
                $child instanceof Strikethrough => '~~'.$this->inlines($child).'~~',
                $child instanceof Image => '!['.$this->inlines($child).']('.$this->target($child->getUrl(), $child->getTitle()).')',
                $child instanceof Link => $this->link($child),
                $child instanceof Newline => $child->getType() === Newline::HARDBREAK ? "\\\n" : "\n",
                $child instanceof HtmlInline => $child->getLiteral(),
                $child instanceof TaskListItemMarker => $child->isChecked() ? '[x]' : '[ ]',
                $child instanceof FootnoteRef => '[^'.$this->label($child->getReference()->getLabel()).']',
                $child instanceof FootnoteBackref => '',
                default => $this->inlines($child),
            };
        }

        return $out;
    }

    private function link(Link $link): string
    {
        $text = $this->inlines($link);
        $url = ($this->url)($link->getUrl());

        if ($text === $link->getUrl() || $text === $url) {
            return '<'.$url.'>';
        }

        return '['.$text.']('.$this->target($link->getUrl(), $link->getTitle()).')';
    }

    private function target(string $url, ?string $title): string
    {
        $url = ($this->url)($url);
        $url = preg_match('/[\s()]/', $url) ? '<'.$url.'>' : $url;

        return $title !== null && $title !== '' ? $url.' "'.str_replace('"', '\"', $title).'"' : $url;
    }

    private function code(string $literal): string
    {
        preg_match_all('/`+/', $literal, $runs);
        $ticks = str_repeat('`', max(1, ...array_map(fn (string $run) => strlen($run) + 1, $runs[0] ?: [''])));
        $pad = str_starts_with($literal, '`') || str_ends_with($literal, '`') ? ' ' : '';

        return $ticks.$pad.$literal.$pad.$ticks;
    }

    /**
     * Escapes only what would change the meaning of plain text: emphasis
     * markers and backticks. Agents read the result, so less noise is better
     * than perfect round-tripping.
     */
    private function escape(string $text): string
    {
        $text = str_replace(['*', '`'], ['\*', '\`'], $text);

        return (string) preg_replace('/(?<![\p{L}\p{N}])_|_(?![\p{L}\p{N}])/u', '\_', $text);
    }

    private function quote(string $markdown): string
    {
        return implode("\n", array_map(fn (string $line) => $line === '' ? '>' : '> '.$line, explode("\n", $markdown)));
    }

    /** Indents every line after the first, so a list item's later blocks stay inside it. */
    private function hang(string $text, string $indent): string
    {
        $lines = explode("\n", $text);

        foreach ($lines as $index => $line) {
            if ($index > 0 && $line !== '') {
                $lines[$index] = $indent.$line;
            }
        }

        return implode("\n", $lines);
    }

    /** A footnote used twice gets a "__2" suffix internally; agents see the label the author wrote. */
    private function label(string $label): string
    {
        return (string) preg_replace('/__\d+$/', '', $label);
    }
}
