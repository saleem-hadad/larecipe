<?php

namespace LaRecipe\Domains\Sync\Enums;

/**
 * What a `larecipe:sync` run is asked to do. The mode picks the pages the agent
 * works on and the skill workflow it follows.
 */
enum SyncMode: string
{
    /** Update the pages a code change left stale (larecipe-update-docs). The default. */
    case Diff = 'diff';

    /** Update the pages named with --page, against the current code (larecipe-update-docs). */
    case Pages = 'pages';

    /** Review the whole set and fix what matters most (larecipe-audit-docs). */
    case Audit = 'audit';

    /** The skill whose workflow the agent follows, from resources/boost/skills. */
    public function skill(): string
    {
        return $this === self::Audit ? 'larecipe-audit-docs' : 'larecipe-update-docs';
    }
}
