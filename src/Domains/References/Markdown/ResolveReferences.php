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

        $this->state->issues[] = [
            'component' => $node->name,
            'message' => "<{$node->name} {$prop}=\"{$target}\"> does not resolve: nothing in the app has that name any more.",
            'rule' => self::RULE,
            'level' => 'error',
            'line' => $node->getStartLine(),
            'target' => $target,
            'fix' => $closest !== null ? "Use {$prop}=\"{$closest}\"." : $this->fix($kind, $node->name),
        ];
    }

    /** How to point a reference at something real when no existing name is close. */
    private function fix(string $kind, string $component): string
    {
        return match ($kind) {
            'route' => "Use the name of an existing route (php artisan route:list shows them), or remove the <{$component}>.",
            'artisan' => "Use an existing command (php artisan list shows them), or remove the <{$component}>.",
            'model' => "Use an Eloquent model that references.models.allow in config/larecipe.php allows, or remove the <{$component}>. In CI without a database, run php artisan larecipe:references:snapshot first so models resolve from the snapshot.",
            default => "Use an existing event class (short names are looked up in references.events.namespaces in config/larecipe.php), or remove the <{$component}>.",
        };
    }
}
