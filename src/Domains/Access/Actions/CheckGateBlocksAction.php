<?php

namespace LaRecipe\Domains\Access\Actions;

use LaRecipe\Domains\Access\Contracts\Authorizer;
use LaRecipe\Domains\Access\DTOs\GateIssue;

/**
 * Checks the abilities a page's <Can> and <Cannot> blocks ask about, for
 * larecipe:validate: an ability the host never defined is a warning (with the
 * closest defined name as the fix), and a gate block on a page that is itself
 * private is reported as redundant.
 *
 * An undefined ability is never an error at render time: it simply denies, so a
 * typo hides a block instead of leaking it.
 */
final class CheckGateBlocksAction
{
    public function __construct(private readonly Authorizer $authorizer) {}

    /**
     * @param  list<string>  $abilities  RenderedPage::$abilities
     * @return list<GateIssue>
     */
    public function execute(array $abilities, bool $pageIsPrivate = false): array
    {
        $issues = [];
        $defined = $this->authorizer->definedAbilities();

        foreach (array_unique($abilities) as $ability) {
            if (! $this->authorizer->defines($ability)) {
                $closest = $this->closest($ability, $defined);

                $issues[] = new GateIssue(
                    GateIssue::UNKNOWN_ABILITY,
                    'warning',
                    $ability,
                    "The ability \"{$ability}\" is not defined, so its gate blocks deny every reader.",
                    $closest !== null
                        ? "Use \"{$closest}\", or define \"{$ability}\" with Gate::define() in a service provider."
                        : "Define \"{$ability}\" with Gate::define() in a service provider.",
                );
            }

            if ($pageIsPrivate) {
                $issues[] = new GateIssue(
                    GateIssue::REDUNDANT_GATE,
                    'warning',
                    $ability,
                    "This page is private, so its readers already passed viewLarecipe. The \"{$ability}\" block may be redundant.",
                );
            }
        }

        return $issues;
    }

    /** @param list<string> $candidates */
    private function closest(string $ability, array $candidates): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $distance = levenshtein(strtolower($ability), strtolower($candidate));

            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$candidate, $distance];
            }
        }

        return $best !== null && $bestDistance <= max(2, intdiv(strlen($ability), 3)) ? $best : null;
    }
}
