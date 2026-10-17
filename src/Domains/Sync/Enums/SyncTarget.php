<?php

namespace LaRecipe\Domains\Sync\Enums;

/**
 * Where a `larecipe:sync` run's changes land, decided by the set's driver and
 * where the run happens (ARCHITECTURE.md 13.6).
 *
 * Inside a repository the change rides in the pull request with the code
 * (the Same-PR Rule): Markdown files for a repository set, the branch's docs
 * migration for a database set. Outside one (a queue worker on production,
 * or --remote) it goes straight to a database set under the review mode.
 */
enum SyncTarget: string
{
    /** A repository set: page files change in the working tree. */
    case WorkingTree = 'working_tree';

    /** A database set inside a repository: the change is applied locally and written into the branch's docs migration. */
    case DocsMigration = 'docs_migration';

    /** A database set with no repository around it: drafts under review: required, live revisions under review: auto. */
    case Database = 'database';

    /** A production database set, through its authenticated remote author tools, under its review mode. */
    case Remote = 'remote';

    /** True when the change travels in the pull request, reviewed there with the code. */
    public function ridesInPullRequest(): bool
    {
        return in_array($this, [self::WorkingTree, self::DocsMigration], true);
    }

    /** How a person reads where the changes go. */
    public function describe(): string
    {
        return match ($this) {
            self::WorkingTree => 'files in the working tree, for you to review and commit with your code',
            self::DocsMigration => "the branch's docs migration, for you to review and commit with your code",
            self::Database => 'the database, under the set\'s review mode',
            self::Remote => 'the remote set, under its review mode',
        };
    }
}
