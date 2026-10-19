<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\Enums\DocsMigrationOperationType as Type;

/**
 * Folds a session's changes into one readable docs migration per branch.
 *
 * Each new change either merges with the last operation on the same subject
 * or is appended. Merging keeps the earliest basedOn hash (what production
 * had before the branch) and the latest content: create then update is one
 * create, update then update is one update, create then delete is nothing.
 * Operations on other subjects never interleave with a merge, so the order
 * of the remaining operations still replays the session faithfully.
 */
final class DocsMigrationOperations
{
    public function __construct(private readonly JsonMergePatch $patch = new JsonMergePatch) {}

    /**
     * @param  list<DocsMigrationOperation>  $operations
     * @return list<DocsMigrationOperation>
     */
    public function merge(array $operations, DocsMigrationOperation $next): array
    {
        $index = $this->lastTouching($operations, $next);
        $previous = $index !== null ? $operations[$index] : null;
        $replacement = $previous !== null ? $this->combine($previous, $next) : false;

        if ($replacement === false) {
            $operations[] = $next;

            return $operations;
        }

        if ($replacement === null) {
            unset($operations[$index]);
        } else {
            $operations[$index] = $replacement;
        }

        return array_values($operations);
    }

    /**
     * The single operation equivalent to $previous then $next, null when they
     * cancel out, or false when they cannot be combined.
     */
    private function combine(DocsMigrationOperation $previous, DocsMigrationOperation $next): DocsMigrationOperation|false|null
    {
        return match (true) {
            $next->type === Type::UpdatePage && $previous->type === Type::CreatePage && $previous->target === $next->target => DocsMigrationOperation::createPage($next->target, $next->frontmatter, (string) $next->body),
            $next->type === Type::UpdatePage && $previous->type === Type::UpdatePage && $previous->target === $next->target => DocsMigrationOperation::updatePage($next->target, $previous->basedOn, $next->frontmatter, (string) $next->body),
            $next->type === Type::CreatePage && $previous->type === Type::DeletePage && $previous->target === $next->target => DocsMigrationOperation::updatePage($next->target, $previous->basedOn, $next->frontmatter, (string) $next->body),
            $next->type === Type::DeletePage && $previous->type === Type::CreatePage && $previous->target === $next->target => null,
            $next->type === Type::DeletePage && $previous->type === Type::UpdatePage && $previous->target === $next->target => DocsMigrationOperation::deletePage($next->target, $previous->basedOn),
            $next->type === Type::MovePage && $previous->type === Type::CreatePage && $previous->target === $next->target => DocsMigrationOperation::createPage((string) $next->to, $previous->frontmatter, (string) $previous->body),
            $next->type === Type::UpdateNavigation && $previous->type === Type::UpdateNavigation && $next->tree !== null => DocsMigrationOperation::updateNavigation($next->target, $previous->basedOn, $next->tree, null, $next->locale),
            $next->type === Type::UpdateNavigation && $previous->type === Type::UpdateNavigation && $previous->tree === null => DocsMigrationOperation::updateNavigation($next->target, $previous->basedOn, null, [...($previous->patch ?? []), ...($next->patch ?? [])], $next->locale),
            $next->type === Type::UpdateManifest && $previous->type === Type::UpdateManifest => DocsMigrationOperation::updateManifest($previous->basedOn, (array) $this->patch->compose($previous->patch ?? [], $next->patch ?? [])),
            default => false,
        };
    }

    /** @param list<DocsMigrationOperation> $operations */
    private function lastTouching(array $operations, DocsMigrationOperation $next): ?int
    {
        for ($index = count($operations) - 1; $index >= 0; $index--) {
            if ($this->touchesSameSubject($operations[$index], $next)) {
                return $index;
            }
        }

        return null;
    }

    private function touchesSameSubject(DocsMigrationOperation $operation, DocsMigrationOperation $next): bool
    {
        return match ($next->type) {
            Type::UpdateNavigation => $operation->type === Type::UpdateNavigation && $operation->target === $next->target && $operation->locale === $next->locale,
            Type::UpdateManifest => $operation->type === Type::UpdateManifest,
            default => $operation->type->subjectType() === $next->type->subjectType()
                && ($operation->target === $next->target || $operation->to === $next->target),
        };
    }
}
