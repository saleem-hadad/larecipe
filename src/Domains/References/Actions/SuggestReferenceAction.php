<?php

namespace LaRecipe\Domains\References\Actions;

use LaRecipe\Domains\References\Contracts\ListsReferenceTargets;
use LaRecipe\Domains\References\Services\ReferenceRegistry;

/**
 * The closest existing target to one that no longer resolves, for the fix hint
 * on a references.missing issue ("orders.create" for "orders.store" after a rename).
 */
final class SuggestReferenceAction
{
    public function __construct(private readonly ReferenceRegistry $registry) {}

    public function execute(string $kind, string $target): ?string
    {
        $resolver = $this->registry->get($kind);

        if (! $resolver instanceof ListsReferenceTargets) {
            return null;
        }

        $needle = strtolower($this->shortName($target));
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($resolver->targets() as $candidate) {
            $distance = levenshtein($needle, strtolower($this->shortName($candidate)));

            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$candidate, $distance];
            }
        }

        return $best !== null && $bestDistance <= max(3, intdiv(strlen($needle), 2)) ? $best : null;
    }

    /** Class names compare by their last segment, so "Order" finds "App\Models\Orders". */
    private function shortName(string $target): string
    {
        $position = strrpos($target, '\\');

        return $position === false ? $target : substr($target, $position + 1);
    }
}
