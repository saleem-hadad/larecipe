<?php

namespace LaRecipe\Domains\Validation\Services;

/**
 * "Did you mean ...?" for fix hints: the closest candidate by edit distance,
 * when it is close enough to be a typo rather than a different word.
 */
final class Suggest
{
    /** @param iterable<string> $candidates */
    public static function closest(string $needle, iterable $candidates, ?int $maxDistance = null): ?string
    {
        $needle = mb_strtolower($needle);
        $limit = $maxDistance ?? max(2, intdiv(mb_strlen($needle), 3));
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $distance = levenshtein($needle, mb_strtolower((string) $candidate));

            if ($distance < $bestDistance && $distance <= $limit) {
                $best = (string) $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }
}
