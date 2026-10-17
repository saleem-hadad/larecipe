<?php

namespace LaRecipe\Domains\Admin\Queries;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\GetRevisionHistoryAction;
use LaRecipe\Domains\Sources\Actions\ListPageSummariesAction;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\DTOs\Subject;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;

/**
 * Under `review: auto`, agents working outside a pull request publish at
 * once. The pass then shows what they published lately, live right now,
 * each one a click away from a revert.
 *
 * It starts from what readers see (pages, the navigation, the manifest whose
 * live revision an agent wrote recently) and reads only those histories, so
 * the cost follows the number of recent agent changes, not the history size.
 */
final class RecentAgentChangesQuery
{
    public const DAYS = 14;

    public const LIMIT = 20;

    /** Channels whose changes were never reviewed in a pull request. */
    private const UNREVIEWED = [Channel::RemoteAgent, Channel::Sync];

    public function __construct(
        private readonly ListPageSummariesAction $summaries,
        private readonly GetPageAction $pages,
        private readonly GetRevisionHistoryAction $history,
    ) {}

    /** @return list<Revision> newest first */
    public function get(DocSet $set): array
    {
        $since = new DateTimeImmutable('-'.self::DAYS.' days');
        $subjects = [];

        foreach ($set->versions() as $version) {
            foreach ($set->locales() as $locale) {
                foreach ($this->recentAgentPages($set->key, $version->id, $locale->code, $since) as $page) {
                    $subjects[] = Subject::page($page->ref);
                }

                $subjects[] = Subject::navigation($set->key, $version->id, $locale->code);
            }
        }

        $subjects[] = Subject::manifest($set->key);
        $revisions = [];

        foreach ($subjects as $subject) {
            $live = $this->live($subject);

            if ($live !== null && $this->isRecentAgentChange($live, $since)) {
                $revisions[] = $live;
            }
        }

        usort($revisions, fn (Revision $a, Revision $b) => (int) $b->id <=> (int) $a->id);

        return array_slice($revisions, 0, self::LIMIT);
    }

    /** @return list<Page> */
    private function recentAgentPages(string $set, string $version, string $locale, DateTimeImmutable $since): array
    {
        $pages = [];

        foreach ($this->summaries->execute($set, $version, $locale) as $summary) {
            if ($summary->updatedAt === null || $summary->updatedAt < $since) {
                continue;
            }

            $page = $this->pages->execute($summary->ref);

            if ($page?->provenance?->actor === Actor::Agent) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    private function live(Subject $subject): ?Revision
    {
        foreach ($this->history->execute($subject) as $revision) {
            if ($revision->is(RevisionStatus::Published)) {
                return $revision;
            }
        }

        return null;
    }

    private function isRecentAgentChange(Revision $revision, DateTimeImmutable $since): bool
    {
        $channel = Channel::tryFrom((string) $revision->channel);

        return $revision->author->actor === Actor::Agent
            && in_array($channel, self::UNREVIEWED, true)
            && ($revision->publishedAt ?? $revision->createdAt) >= $since;
    }
}
