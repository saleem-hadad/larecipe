<?php

namespace LaRecipe\Domains\Admin\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use LaRecipe\Domains\Admin\Actions\BuildReviewQueueAction;
use LaRecipe\Domains\Admin\Actions\DescribeRevisionAction;
use LaRecipe\Domains\Admin\Actions\ReplaceDraftAction;
use LaRecipe\Domains\Admin\DTOs\DraftReplacement;
use LaRecipe\Domains\Admin\Laravel\AdminResponses;
use LaRecipe\Domains\Admin\Requests\AdminRequest;
use LaRecipe\Domains\Admin\Requests\RejectRequest;
use LaRecipe\Domains\Admin\Requests\ReplaceDraftRequest;
use LaRecipe\Domains\Admin\Requests\ScopeRequest;
use LaRecipe\Domains\Admin\Support\AdminJson;
use LaRecipe\Domains\Sources\Actions\GetRevisionAction;
use LaRecipe\Domains\Sources\Actions\PublishDraftAction;
use LaRecipe\Domains\Sources\Actions\RejectDraftAction;
use LaRecipe\Domains\Sources\DTOs\ReviewDecision;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;

/**
 * The pass: the review queue and the three decisions a reviewer makes on a
 * ticket. Publish it (for a conflict, "take the migration"), reject it with
 * a reason ("keep production"), or edit it and publish their own version
 * ("edit a merge").
 */
final class ReviewController
{
    public function __construct(
        private readonly BuildReviewQueueAction $queue,
        private readonly GetRevisionAction $revisions,
        private readonly DescribeRevisionAction $describe,
        private readonly PublishDraftAction $publish,
        private readonly RejectDraftAction $reject,
        private readonly ReplaceDraftAction $replace,
        private readonly AdminResponses $responses,
    ) {}

    public function index(ScopeRequest $request): JsonResponse
    {
        return new JsonResponse($this->queue->execute($request->docSet()));
    }

    public function show(ScopeRequest $request, string $revision): JsonResponse
    {
        return $this->withRevision($request, $revision, false, fn (Revision $found) => new JsonResponse(
            $this->describe->execute($request->docSet(), $found),
        ));
    }

    public function publish(ScopeRequest $request, string $revision): JsonResponse
    {
        return $this->withRevision($request, $revision, true, fn (Revision $found) => $this->responses->write(
            $this->publish->execute(new ReviewDecision($request->docSet()->key, (string) $found->id, $request->author())),
        ));
    }

    public function reject(RejectRequest $request, string $revision): JsonResponse
    {
        return $this->withRevision($request, $revision, true, function (Revision $found) use ($request) {
            $reason = $request->reason() ?? ($found->is(RevisionStatus::Conflict) ? 'Kept production.' : null);
            $rejected = $this->reject->execute(new ReviewDecision($request->docSet()->key, (string) $found->id, $request->author(), $reason));

            return new JsonResponse(['outcome' => 'rejected', 'revision' => AdminJson::revision($rejected)]);
        });
    }

    public function edit(ReplaceDraftRequest $request, string $revision): JsonResponse
    {
        return $this->withRevision($request, $revision, true, fn (Revision $found) => $this->responses->write($this->replace->execute(new DraftReplacement(
            set: $request->docSet()->key,
            draft: $found,
            author: $request->author(),
            frontmatter: is_array($request->input('frontmatter')) ? $request->input('frontmatter') : null,
            body: $request->markdown(),
            tree: is_array($request->input('tree')) ? $request->input('tree') : null,
            manifest: is_array($request->input('manifest')) ? $request->input('manifest') : null,
            summary: $request->summary(),
            mode: $request->mode(),
        ))));
    }

    /**
     * Find the revision in this set (404 when it is not there) and, for a
     * decision, make sure it still waits for one (409 when someone decided
     * first), then run the step.
     *
     * @param  Closure(Revision): JsonResponse  $step
     */
    private function withRevision(AdminRequest $request, string $id, bool $pending, Closure $step): JsonResponse
    {
        $revision = $this->revisions->execute($request->docSet()->key, $id);

        if ($revision === null) {
            return $this->responses->message("There is no revision #{$id} in this doc set.", 404);
        }

        if ($pending && ! $revision->isPending()) {
            return $this->responses->message("Revision #{$id} is {$revision->status} already, so there is nothing to decide. Someone may have reviewed it first.", 409);
        }

        return $this->responses->guard(fn () => $step($revision));
    }
}
