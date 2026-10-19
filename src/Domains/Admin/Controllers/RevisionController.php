<?php

namespace LaRecipe\Domains\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use LaRecipe\Domains\Admin\Actions\DescribeRevisionAction;
use LaRecipe\Domains\Admin\Laravel\AdminResponses;
use LaRecipe\Domains\Admin\Requests\RevisionHistoryRequest;
use LaRecipe\Domains\Admin\Requests\ScopeRequest;
use LaRecipe\Domains\Admin\Support\AdminJson;
use LaRecipe\Domains\Sources\Actions\GetRevisionAction;
use LaRecipe\Domains\Sources\Actions\GetRevisionHistoryAction;
use LaRecipe\Domains\Sources\Actions\RevertPageAction;
use LaRecipe\Domains\Sources\DTOs\RevertRequest;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;

/**
 * The history of a page, a version's navigation or the manifest: every
 * revision (including the ones docs migrations created), each with a diff
 * against the one it replaced, and a revert that restores it as a new
 * published revision, so a revert can itself be reverted.
 */
final class RevisionController
{
    public function __construct(
        private readonly GetRevisionHistoryAction $history,
        private readonly GetRevisionAction $revisions,
        private readonly DescribeRevisionAction $describe,
        private readonly RevertPageAction $revert,
        private readonly AdminResponses $responses,
    ) {}

    public function index(RevisionHistoryRequest $request): JsonResponse
    {
        $revisions = $this->history->execute($request->subject());

        return new JsonResponse([
            'subject' => $request->subject()->key(),
            'revisions' => array_map(fn (Revision $revision) => AdminJson::revision($revision) + [
                'restorable' => $this->restorable($revision),
            ], $revisions),
        ]);
    }

    public function show(ScopeRequest $request, string $revision): JsonResponse
    {
        $found = $this->revisions->execute($request->docSet()->key, $revision);

        if ($found === null) {
            return $this->responses->message("There is no revision #{$revision} in this doc set.", 404);
        }

        return new JsonResponse($this->describe->execute($request->docSet(), $found));
    }

    public function revert(ScopeRequest $request, string $revision): JsonResponse
    {
        $found = $this->revisions->execute($request->docSet()->key, $revision);

        if ($found === null) {
            return $this->responses->message("There is no revision #{$revision} in this doc set.", 404);
        }

        if (! $this->restorable($found)) {
            return $this->responses->message($found->is(RevisionStatus::Published)
                ? "Revision #{$revision} is what readers see now, so there is nothing to restore."
                : "Revision #{$revision} was never published ({$found->status}). Publish it from the review queue instead.", 422);
        }

        return $this->responses->guard(fn () => $this->responses->write($this->revert->execute(new RevertRequest(
            set: $request->docSet()->key,
            revisionId: (string) $found->id,
            by: $request->author(),
            channel: Channel::AdminUi,
        ))));
    }

    /** Only a state readers once saw can be brought back; drafts go through the review queue. */
    private function restorable(Revision $revision): bool
    {
        return $revision->is(RevisionStatus::Superseded) || $revision->is(RevisionStatus::Reverted);
    }
}
