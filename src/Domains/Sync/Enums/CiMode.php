<?php

namespace LaRecipe\Domains\Sync\Enums;

/**
 * How the optional no-AI CI check reports stale pages on a pull request.
 */
enum CiMode: string
{
    /** A failing check; the skip-docs label lets a pull request through. */
    case Fail = 'fail';

    /** A comment listing the stale pages; the check never fails. */
    case Comment = 'comment';
}
