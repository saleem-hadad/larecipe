<?php

namespace LaRecipe\Domains\Sync\Enums;

enum AffectedFormat: string
{
    case Text = 'text';
    case Json = 'json';

    /** GitHub Actions annotations plus a job summary. */
    case Github = 'github';

    /** A Markdown list, for a pull request comment. */
    case Markdown = 'markdown';
}
