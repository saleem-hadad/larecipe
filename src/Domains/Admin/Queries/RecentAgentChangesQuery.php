<?php

namespace LaRecipe\Domains\Admin\Queries;

use DateTimeImmutable;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Actions\ListRevisionsAction;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;

/**
 * Under `review: auto`, agents working outside a pull request publish at
 * once. The pass then shows what they published lately that readers still
 * see (a revision that is still the live one), each a click from a revert.
 */
final class RecentAgentChangesQuery
{
    public const DAYS = 14;

    public const LIMIT = 20;

    /** How far back to look, in revisions. */
    private const SCAN = 300;

    /** Channels whose changes were never reviewed in a pull request. */
    private const UNREVIEWED = [Channel::RemoteAgent, Channel::Sync];

    public function __construct(private readonly ListRevisionsAction $revisions) {}

    /** @return list<Revision> newest first */
    public function get(string $set): array
    {
        $since = new DateTimeImmutable('-'.self::DAYS.' days');
        $recent = array_filter(
            $this->revisions->execute($set, null, self::SCAN),
            fn (Revision $revision) => $revision->is(RevisionStatus::Published)
                && $revision->author->actor === Actor::Agent
                && in_array(Channel::tryFrom((string) $revision->channel), self::UNREVIEWED, true)
                && ($revision->publishedAt ?? $revision->createdAt) >= $since,
        );

        return array_slice(array_values($recent), 0, self::LIMIT);
    }
}
