<?php

namespace LaRecipe\Domains\Agents\Laravel;

use Illuminate\Http\Request;
use LaRecipe\Domains\Delivery\Support\RequestClassifier;

/**
 * The request kind (human, crawler, agent) every traffic event carries.
 *
 * llms.txt, skill.md and the skills index exist for agents, so a fetch that
 * does not ask for HTML counts as an agent unless its user agent says crawler.
 * Classification feeds analytics only; it never changes what is served.
 */
final class TrafficKind
{
    public function __construct(private readonly RequestClassifier $classifier) {}

    public function forAgentSurface(Request $request): string
    {
        $wantsHtml = str_contains(strtolower((string) $request->header('Accept')), 'text/html');

        return $this->classifier->classify($request, $wantsHtml ? null : 'markdown');
    }

    public function of(Request $request): string
    {
        return $this->classifier->classify($request);
    }
}
