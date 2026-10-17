<?php

namespace LaRecipe\Domains\Sources\Contracts;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\DTOs\Revision;

interface DraftableContentSource extends WritableContentSource
{
    /** @return iterable<Revision> drafts, including conflicts raised by docs migrations */
    public function drafts(?string $version = null): iterable;

    public function publish(Revision $draft, Provenance $by): void;

    public function reject(Revision $draft, Provenance $by, ?string $reason = null): void;

    public function revert(PageRef $page, Revision $to, Provenance $by): Revision;
}
