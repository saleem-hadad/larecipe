<?php

namespace LaRecipe\Domains\References\Markdown;

use LaRecipe\Domains\Markdown\Parser\ComponentNode;
use LaRecipe\Domains\Markdown\Services\RenderState;
use LaRecipe\Domains\References\Actions\ResolveReferenceAction;
use LaRecipe\Domains\References\Actions\SuggestReferenceAction;
use League\CommonMark\Event\DocumentParsedEvent;

/**
 * Resolves every live reference on a page once, right after parsing.
 *
 * It marks the page as reading the app (RenderState::$hasLiveReferences), so the
 * render cache keys it by the references fingerprint, and it reports each
 * reference that no longer resolves as a references.missing issue with the
 * closest existing name as the fix. Gate blocks are filtered first, so a
 * reference inside a block the reader cannot see is never resolved for them.
 */
final class ResolveReferences
{
    public const PRIORITY = 50;

    public const RULE = 'references.missing';

    public function __construct(
        private readonly RenderState $state,
        private readonly ResolveReferenceAction $resolve,
        private readonly SuggestReferenceAction $suggest,
    ) {}

    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if (! $node instanceof ComponentNode || ! isset(ReferenceComponents::COMPONENTS[$node->name])) {
                continue;
            }

            [$kind, $prop] = ReferenceComponents::COMPONENTS[$node->name];
            $target = trim((string) ($node->props[$prop] ?? ''));
            $this->state->hasLiveReferences = true;

            $reference = $target === '' ? null : $this->resolve->execute($kind, $target, $node->props);
            $node->props[ReferenceComponents::RESOLVED] = $reference?->toArray();

            // A missing prop is already reported by the component's prop validation.
            if ($reference === null && $target !== '') {
                $this->missing($node, $kind, $prop, $target);
            }
        }
    }

    private function missing(ComponentNode $node, string $kind, string $prop, string $target): void
    {
        $closest = $this->suggest->execute($kind, $target);

        $this->state->issues[] = array_filter([
            'component' => $node->name,
            'message' => "<{$node->name} {$prop}=\"{$target}\"> does not resolve: nothing in the app has that name any more.",
            'rule' => self::RULE,
            'level' => 'error',
            'line' => $node->getStartLine(),
            'target' => $target,
            'fix' => $closest !== null ? "Use {$prop}=\"{$closest}\"." : null,
        ], fn ($value) => $value !== null);
    }
}
