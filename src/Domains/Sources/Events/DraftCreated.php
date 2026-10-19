<?php

namespace LaRecipe\Domains\Sources\Events;

use LaRecipe\Domains\Sources\DTOs\Revision;

/**
 * A change is waiting in the review queue (review: required, or a person chose
 * to save a draft). The admin UI and host listeners use it to notify reviewers.
 */
final readonly class DraftCreated
{
    public function __construct(public Revision $revision) {}
}
