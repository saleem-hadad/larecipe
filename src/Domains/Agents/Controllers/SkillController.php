<?php

namespace LaRecipe\Domains\Agents\Controllers;

use Illuminate\Http\Request;
use LaRecipe\Domains\Agents\Actions\RenderSkillMdAction;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Laravel\AgentResponses;
use LaRecipe\Domains\Agents\Laravel\TrafficKind;
use LaRecipe\Domains\Agents\Services\SkillMdBuilder;
use LaRecipe\Domains\Delivery\Events\SkillServed;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Support\Contracts\Dispatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves /{prefix}/skill.md: the agent skill for using this set's product.
 */
final class SkillController
{
    public function __construct(
        private readonly SetFromRequest $current,
        private readonly AgentDocs $docs,
        private readonly RenderSkillMdAction $render,
        private readonly SkillMdBuilder $skills,
        private readonly AgentResponses $responses,
        private readonly TrafficKind $kind,
        private readonly Dispatcher $events,
    ) {}

    public function __invoke(Request $request): Response
    {
        $set = $this->docs->set($this->current->key($request));

        if ($set === null) {
            return $this->responses->notFound('This skill');
        }

        $this->events->dispatch(new SkillServed($set->key, $this->skills->name($set), $this->kind->forAgentSurface($request), 'skill.md', $request->userAgent()));

        return $this->responses->make($this->render->execute($set), AgentResponses::MARKDOWN, $this->docs->isSharedView($set), $request);
    }
}
