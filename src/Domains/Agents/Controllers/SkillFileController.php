<?php

namespace LaRecipe\Domains\Agents\Controllers;

use Illuminate\Http\Request;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Laravel\AgentResponses;
use LaRecipe\Domains\Agents\Laravel\TrafficKind;
use LaRecipe\Domains\Agents\Services\SkillLibrary;
use LaRecipe\Domains\Delivery\Events\SkillServed;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Support\Contracts\Dispatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves hand-written skills from the set's skills/ folder:
 * /{prefix}/skills/{name}/SKILL.md and the files it references.
 *
 * Everything is served as text with nosniff, so a skill's scripts are readable
 * but never executed by a browser.
 */
final class SkillFileController
{
    private const TYPES = [
        'md' => AgentResponses::MARKDOWN,
        'json' => 'application/json; charset=UTF-8',
    ];

    public function __construct(
        private readonly SetFromRequest $current,
        private readonly AgentDocs $docs,
        private readonly SkillLibrary $library,
        private readonly AgentResponses $responses,
        private readonly TrafficKind $kind,
        private readonly Dispatcher $events,
    ) {}

    public function __invoke(Request $request): Response
    {
        $set = $this->docs->set($this->current->key($request));
        $name = (string) $request->route('name');
        $file = (string) $request->route('file');
        $path = $set === null ? null : $this->library->file($set, $name, $file);

        if ($set === null || $path === null) {
            return $this->responses->notFound('This skill file');
        }

        $this->events->dispatch(new SkillServed($set->key, $name, $this->kind->forAgentSurface($request), $file, $request->userAgent()));

        $type = self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? AgentResponses::TEXT;

        return $this->responses->make((string) file_get_contents($path), $type, $this->docs->isSharedView($set), $request);
    }
}
