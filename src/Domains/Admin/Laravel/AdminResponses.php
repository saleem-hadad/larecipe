<?php

namespace LaRecipe\Domains\Admin\Laravel;

use Closure;
use Illuminate\Http\JsonResponse;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\WriteOutcome;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Exceptions\ReadOnlySource;
use LaRecipe\Domains\Sources\Exceptions\RevisionNotFound;
use LaRecipe\Domains\Sources\Exceptions\UnsafePath;

/**
 * How the admin API answers a change, the same way for every endpoint, so the
 * app has one place that reads them:
 *
 * - 200 (201 for a new page): published, saved as a draft, or already so.
 * - 409: the content changed since it was read, or the draft is no longer pending.
 * - 422: refused (validation errors, a taken path, an unsafe path).
 *
 * The body is always the write result: outcome, message, revision, validation.
 */
final class AdminResponses
{
    public function write(WriteResult $result, bool $created = false): JsonResponse
    {
        $status = match ($result->outcome) {
            WriteOutcome::Published, WriteOutcome::Drafted => $created ? 201 : 200,
            WriteOutcome::Unchanged => 200,
            WriteOutcome::Conflicted => 409,
            WriteOutcome::Rejected => 422,
        };

        return new JsonResponse($result->toArray(), $status);
    }

    /**
     * Run a change and turn the Sources domain's refusals into answers a
     * person can act on, instead of a server error.
     *
     * @param  Closure(): JsonResponse  $change
     */
    public function guard(Closure $change): JsonResponse
    {
        try {
            return $change();
        } catch (RevisionNotFound $exception) {
            return $this->message($exception->getMessage(), 409);
        } catch (InvalidContentChange|UnsafePath|ReadOnlySource $exception) {
            return $this->message($exception->getMessage(), 422);
        }
    }

    public function message(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
