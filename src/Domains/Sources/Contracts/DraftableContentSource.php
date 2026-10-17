<?php

namespace LaRecipe\Domains\Sources\Contracts;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\DTOs\Subject;
use LaRecipe\Domains\Sources\DTOs\WriteContext;

/**
 * A source that keeps revisions: drafts, conflicts, publishing, rejecting and
 * restoring. The database driver implements it; the repository driver does not,
 * because git and the pull request already do all of this.
 */
interface DraftableContentSource extends WritableContentSource
{
    /** @return iterable<Revision> drafts, including conflicts raised by docs migrations, oldest first */
    public function drafts(?string $version = null): iterable;

    public function publish(Revision $draft, Provenance $by): void;

    public function reject(Revision $draft, Provenance $by, ?string $reason = null): void;

    public function revert(PageRef $page, Revision $to, Provenance $by): Revision;

    /**
     * Make the subject of $to look the way it did right after $to, as a new
     * published revision. Works for pages, navigation and the manifest; the
     * revision it replaces is marked reverted.
     */
    public function restore(Revision $to, Provenance $by, ?WriteContext $context = null): Revision;

    public function revision(string $id): ?Revision;

    /** @return iterable<Revision> newest first, across moves of the same page */
    public function history(Subject $subject): iterable;
}
