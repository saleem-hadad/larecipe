<?php

namespace LaRecipe\Domains\Sources\Enums;

/**
 * Where a change came from. The review mode depends on it: a change that rides
 * in a pull request is reviewed there, a change made outside one may need a
 * person to publish it.
 */
enum Channel: string
{
    /** The owner's coding session: repository files or the branch's docs migration. */
    case PullRequest = 'pull_request';

    /** A person editing in the admin UI. */
    case AdminUi = 'admin_ui';

    /** An agent using the authenticated web MCP author tools, outside any pull request. */
    case RemoteAgent = 'remote_agent';

    /** The server-side docs agent (larecipe:sync) writing straight to a database set. */
    case Sync = 'sync';

    /** A docs migration applied by `php artisan migrate`, or a source move. */
    case Migration = 'migration';

    /** True when the change was already reviewed in a pull request. */
    public function isReviewedElsewhere(): bool
    {
        return in_array($this, [self::PullRequest, self::Migration], true);
    }
}
