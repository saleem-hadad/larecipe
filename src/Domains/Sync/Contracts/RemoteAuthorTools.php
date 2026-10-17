<?php

namespace LaRecipe\Domains\Sync\Contracts;

/**
 * The author tools of a production database set, reached over its
 * authenticated MCP endpoint, for `larecipe:sync --remote`: the one way the
 * docs agent changes a set outside a pull request. The remote app applies
 * its own review mode (drafts under required, revisions under auto) and its
 * own manageLarecipe gate.
 */
interface RemoteAuthorTools
{
    /**
     * The remote set's write tools (write_page, move_page, delete_page,
     * update_navigation, update_manifest), as tool objects the docs agent can
     * call. Throws SyncRefused, with what to do, when they are out of reach.
     *
     * @return list<object>
     */
    public function tools(string $url): array;
}
