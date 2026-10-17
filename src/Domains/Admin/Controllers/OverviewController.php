<?php

namespace LaRecipe\Domains\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use LaRecipe\Domains\Admin\Actions\BuildOverviewAction;
use LaRecipe\Domains\Admin\Laravel\IsolatedDoctor;
use LaRecipe\Domains\Admin\Requests\ScopeRequest;
use LaRecipe\Domains\Sync\Actions\FindAffectedPagesAction;
use LaRecipe\Domains\Sync\DTOs\AffectedQuery;
use Throwable;

/**
 * The overview cards, each its own request so the slow ones never hold the
 * screen: the state of the docs (counts and the validation report), the
 * agent-readiness grade (the doctor report), and stale pages (pages whose
 * sources changed after them, from the same computation larecipe:affected
 * and the pre-push hook use).
 */
final class OverviewController
{
    public function __construct(
        private readonly BuildOverviewAction $overview,
        private readonly IsolatedDoctor $doctor,
        private readonly FindAffectedPagesAction $affected,
    ) {}

    public function overview(ScopeRequest $request): JsonResponse
    {
        return new JsonResponse($this->overview->execute($request->docSet(), $request->version()));
    }

    public function readiness(ScopeRequest $request): JsonResponse
    {
        return new JsonResponse($this->doctor->examine($request->docSet())->toArray());
    }

    public function stale(ScopeRequest $request): JsonResponse
    {
        $set = $request->docSet();

        try {
            $report = $this->affected->execute(new AffectedQuery(set: $set->key, version: $request->version()));
        } catch (Throwable $exception) {
            // No git, no history, a shallow clone: say so instead of failing the overview.
            return new JsonResponse(['pages' => [], 'drift' => [], 'notes' => ['Stale pages could not be computed: '.$exception->getMessage()]]);
        }

        return new JsonResponse($report->toArray());
    }
}
