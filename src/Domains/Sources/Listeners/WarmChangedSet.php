<?php

namespace LaRecipe\Domains\Sources\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use LaRecipe\Domains\Sources\Actions\WarmCacheAction;
use LaRecipe\Domains\Sources\Events\ContentChanged;

/**
 * After a publish, warms the set's cache in the background, so the first
 * reader after a change does not pay for a cold page. Queued after the
 * database transaction commits, so it never caches a change that rolled back.
 */
final class WarmChangedSet implements ShouldQueueAfterCommit
{
    public function __construct(private readonly WarmCacheAction $warm) {}

    public function handle(ContentChanged $event): void
    {
        $this->warm->execute($event->set);
    }
}
