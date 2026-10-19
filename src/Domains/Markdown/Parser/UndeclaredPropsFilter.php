<?php

namespace LaRecipe\Domains\Markdown\Parser;

use Closure;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use League\CommonMark\Event\DocumentParsedEvent;

/**
 * Drops the props a component does not declare, right after parsing and
 * before anything else reads the tree, so only declared props reach the
 * fallback HTML, the island and agent Markdown.
 *
 * Built-in components always: they read only what they declare, and their
 * derived props (a Video embed, Tabs labels, a diagram source) must come from
 * LaRecipe, never from the author. Every component on a doc set whose authors
 * may be less trusted than the codebase (a database set). A host component on
 * a repository set keeps accepting data LaRecipe does not describe.
 */
final class UndeclaredPropsFilter
{
    /** Runs before the gate block filter (100) and everything after it. */
    public const PRIORITY = 200;

    /** @param Closure(): bool $strict whether every component on the page is held to its declared props */
    public function __construct(
        private readonly ComponentRegistry $components,
        private readonly Closure $strict,
    ) {}

    public function __invoke(DocumentParsedEvent $event): void
    {
        $strict = ($this->strict)();

        foreach ($event->getDocument()->iterator() as $node) {
            if (! $node instanceof ComponentNode) {
                continue;
            }

            $definition = $this->components->get($node->name);

            if ($definition !== null && ($strict || $definition->origin === 'builtin')) {
                $node->props = array_intersect_key($node->props, $definition->props);
            }
        }
    }
}
