<?php

namespace LaRecipe\Domains\Sources\Events;

use LaRecipe\Domains\Sources\DTOs\Revision;

/**
 * A page was written, moved, deleted or restored, live or as a draft. The
 * revision says which, by whom and through which channel.
 */
final readonly class PageWritten
{
    public function __construct(public Revision $revision) {}
}
