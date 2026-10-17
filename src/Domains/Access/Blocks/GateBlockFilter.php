<?php

namespace LaRecipe\Domains\Access\Blocks;

use LaRecipe\Domains\Access\DTOs\Audience;
use LaRecipe\Domains\Markdown\Parser\ComponentNode;
use LaRecipe\Domains\Markdown\Services\RenderState;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;

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
 */
final class GateBlockFilter
{
    /** Runs before DocumentProcessor (-50), so removed headings never reach the TOC. */
    public const PRIORITY = 100;

    public function __construct(private readonly RenderState $state) {}

    public function __invoke(DocumentParsedEvent $event): void
    {
        $audience = Audience::fromContext($this->state->context);
        $log = $this->state->context[GateBlockLog::CONTEXT_KEY] ?? null;
        $blocks = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof ComponentNode && in_array($node->name, GateBlocks::NAMES, true)) {
                $blocks[] = $node;
            }
        }

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
                if ($log instanceof GateBlockLog) {
                    $log->keep($block->getStartLine(), $block->getEndLine(), $block->selfClosing);
                }

                continue;
            }

            if ($log instanceof GateBlockLog) {
                $log->remove($block->getStartLine(), $block->getEndLine());
            }

            $parent = $block->parent();
            $block->detach();

            // A quote that only held the removed block would render as an empty box.
            if ($parent instanceof BlockQuote && $parent->firstChild() === null) {
                $parent->detach();
            }
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
}
