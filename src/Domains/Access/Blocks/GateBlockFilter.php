<?php

namespace LaRecipe\Domains\Access\Blocks;

use LaRecipe\Domains\Access\DTOs\Audience;
use LaRecipe\Domains\Markdown\Parser\ComponentNode;
use LaRecipe\Domains\Markdown\Services\RenderState;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;

/**
 * Removes the <Can> and <Cannot> blocks a reader may not see from the AST, right
 * after parsing and before anything else reads the tree.
 *
 * This is the Same-Rules Rule at block level. Because the blocks are gone from
 * the document itself (not hidden with CSS), every projection of it agrees: the
 * article HTML, its table of contents and heading ids, agent Markdown, search
 * records and the links validation sees. The reader comes from the render
 * context (Audience::CONTEXT_KEY); without one the reader is a guest. A search
 * parse (purpose "search") keeps every block, because search splits records at
 * the gate nodes and filters each audience's index itself.
 *
 * Every ability the page asks about is recorded in RenderState::$abilities,
 * including those nested in removed blocks, so callers can evaluate them all and
 * pick the cached render for the reader's audience.
 *
 * A gate tag the block parser could not read (inside a sentence, a table or a
 * heading, split over lines, lowercase, or a quote line without ">") fails
 * closed: the block around it, and everything up to the closing tag meant for
 * it, is removed for every reader and reported as access.gate-syntax.
 */
final class GateBlockFilter
{
    /** Runs before DocumentProcessor (-50), so removed headings never reach the TOC. */
    public const PRIORITY = 100;

    public const SYNTAX_RULE = 'access.gate-syntax';

    /** A <Can>, </Can>, <Cannot> or </Cannot> tag left in raw HTML, in any case. */
    private const UNREAD_TAG = '/<(\/?)\s*(can|cannot)\b/i';

    public function __construct(private readonly RenderState $state) {}

    public function __invoke(DocumentParsedEvent $event): void
    {
        $audience = Audience::fromContext($this->state->context);
        $log = $this->state->context[GateBlockLog::CONTEXT_KEY] ?? null;
        $log = $log instanceof GateBlockLog ? $log : null;
        $blocks = [];
        $tags = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof ComponentNode && in_array($node->name, GateBlocks::NAMES, true)) {
                $blocks[] = $node;
                $tags[] = $node;
            } elseif (($node instanceof HtmlBlock || $node instanceof HtmlInline) && preg_match(self::UNREAD_TAG, $node->getLiteral()) === 1) {
                $tags[] = $node;
            }
        }

        // Worked out before anything is removed, while every node is still in place.
        $unreadable = $this->unreadable($tags);

        foreach ($blocks as $block) {
            $ability = $this->ability($block);

            if ($ability !== null) {
                $this->state->abilities[$ability] = true;
            }
        }

        foreach ($blocks as $block) {
            $ability = $this->ability($block);

            if ($ability === null) {
                $this->state->componentIssue($block->name, "<{$block->name}> needs an \"ability\" prop. The block is hidden from every reader until it has one.");
            }

            if ($this->shows($block, $ability, $audience)) {
                $log?->keep($block->getStartLine(), $block->getEndLine(), $block->selfClosing);

                continue;
            }

            $log?->remove($block->getStartLine(), $block->getEndLine());

            $parent = $block->parent();
            $block->detach();

            // A quote that only held the removed block would render as an empty box.
            if ($parent instanceof BlockQuote && $parent->firstChild() === null) {
                $parent->detach();
            }
        }

        if ($unreadable !== []) {
            $this->hide($event->getDocument(), $unreadable, $log);
        }
    }

    private function shows(ComponentNode $block, ?string $ability, Audience $audience): bool
    {
        if ($audience->unrestricted) {
            return true;
        }

        // A block without an ability fails closed: nobody can be shown to hold it.
        if ($ability === null) {
            return false;
        }

        return $block->name === GateBlocks::CAN ? $audience->allows($ability) : ! $audience->allows($ability);
    }

    private function ability(ComponentNode $block): ?string
    {
        $ability = $block->props['ability'] ?? null;

        return is_string($ability) && trim($ability) !== '' ? trim($ability) : null;
    }

    /**
     * The line ranges to remove because of gate tags the parser could not read:
     * the block around each such tag, and everything from an opening tag (or a
     * gate block that ended without its closing tag) to the closing tag meant
     * for it. Each one is reported where it starts.
     *
     * @param  list<Node>  $tags  gate blocks and unread gate tags, in document order
     * @return list<array{0: int, 1: int}>
     */
    private function unreadable(array $tags): array
    {
        $ranges = [];
        $open = [];

        foreach ($tags as $node) {
            if ($node instanceof ComponentNode) {
                if ($node->unclosed && $node->getStartLine() !== null) {
                    $open[] = $node->getStartLine();
                    $this->report($node->name, $node->getStartLine(), "<{$node->name}> has no </{$node->name}> on a line of its own, so its block runs on to the end of the page, quote or list.");
                }

                continue;
            }

            $block = $this->enclosingBlock($node);
            [$start, $end] = [(int) $block->getStartLine(), (int) $block->getEndLine()];
            $ranges[] = [$start, $end];

            preg_match_all(self::UNREAD_TAG, $node->getLiteral(), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $closing, $name]) {
                if ($closing === '') {
                    $open[] = $start;
                } elseif ($open !== []) {
                    $ranges[] = [array_pop($open), $end];
                }
            }

            $name = strtolower($matches[0][2]) === 'cannot' ? GateBlocks::CANNOT : GateBlocks::CAN;
            $this->report($name, $start, "LaRecipe cannot read this <{$name}> tag as a gate block, so the text around it is hidden from every reader until it is fixed.");
        }

        return $ranges;
    }

    /** The nearest block around a raw tag that knows its lines (a table, not one of its cells). */
    private function enclosingBlock(Node $node): AbstractBlock
    {
        for ($block = $node; $block !== null; $block = $block->parent()) {
            if ($block instanceof AbstractBlock && ! $block instanceof Document && $block->getStartLine() !== null && $block->getEndLine() !== null) {
                return $block;
            }
        }

        // Every parsed block knows its lines; a tag without one hides the whole page.
        return $node instanceof AbstractBlock ? $node : new Document;
    }

    /**
     * Detaches every block inside one of the ranges, outermost first, and logs
     * it for the source view.
     *
     * @param  list<array{0: int, 1: int}>  $ranges
     */
    private function hide(Node $parent, array $ranges, ?GateBlockLog $log): void
    {
        foreach ($parent->children() as $child) {
            if (! $child instanceof AbstractBlock) {
                continue;
            }

            [$start, $end] = [$child->getStartLine(), $child->getEndLine()];

            if ($start !== null && $end !== null && $this->within($start, $end, $ranges)) {
                $log?->remove($start, $end);
                $child->detach();

                continue;
            }

            $this->hide($child, $ranges, $log);

            if ($child instanceof BlockQuote && $child->firstChild() === null) {
                $log?->remove($start, $end);
                $child->detach();
            }
        }
    }

    /** @param list<array{0: int, 1: int}> $ranges */
    private function within(int $start, int $end, array $ranges): bool
    {
        foreach ($ranges as [$from, $to]) {
            if ($start >= $from && $end <= $to) {
                return true;
            }
        }

        return false;
    }

    private function report(string $name, int $line, string $message): void
    {
        $this->state->issues[] = [
            'rule' => self::SYNTAX_RULE,
            'level' => RenderState::ERROR,
            'message' => $message,
            'component' => $name,
            'line' => $line,
            'fix' => "Write <{$name} ability=\"...\"> and </{$name}> each on their own line, spelled with a capital letter, with a blank line before and after the block. Inside a quote, start every line of the block with >.",
        ];
    }
}
