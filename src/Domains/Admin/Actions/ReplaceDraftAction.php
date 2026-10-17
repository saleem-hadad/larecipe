<?php

namespace LaRecipe\Domains\Admin\Actions;

use LaRecipe\Domains\Admin\DTOs\DraftReplacement;
use LaRecipe\Domains\Admin\DTOs\SettingsChange;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Sources\Actions\RejectDraftAction;
use LaRecipe\Domains\Sources\Actions\UpdateNavigationAction;
use LaRecipe\Domains\Sources\Actions\WritePageAction;
use LaRecipe\Domains\Sources\DTOs\NavigationDraft;
use LaRecipe\Domains\Sources\DTOs\PageDraft;
use LaRecipe\Domains\Sources\DTOs\ReviewDecision;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\RevisionOperation;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Sources\Enums\SubjectType;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * "Edit" on a draft and "edit a merge" on a conflict: the reviewer writes
 * their own version through the normal write path, then the draft they
 * replaced leaves the queue as rejected, pointing at the new revision.
 *
 * The write is made against the version the draft was made against: for a
 * conflict, production as it was when the docs migration ran (the conflict's
 * content_hash); for a draft, production when the draft was written. If
 * production moved on since, nothing is overwritten (409) and the reviewer
 * sees the newer state first. Rejecting comes second, so a refused write
 * never loses the draft.
 */
final class ReplaceDraftAction
{
    public function __construct(
        private readonly WritePageAction $pages,
        private readonly UpdateNavigationAction $navigation,
        private readonly SaveSettingsAction $settings,
        private readonly RejectDraftAction $reject,
    ) {}

    public function execute(DraftReplacement $replacement): WriteResult
    {
        $draft = $replacement->draft;
        $basedOn = $draft->is(RevisionStatus::Conflict) ? ($draft->conflict['content_hash'] ?? null) : $draft->basedOnHash;
        $summary = $replacement->summary ?? $this->summary($draft);

        $result = match ($draft->type()) {
            SubjectType::Page => $this->page($replacement, $basedOn, $summary),
            SubjectType::Navigation => $this->navigation($replacement, $basedOn, $summary),
            SubjectType::Manifest => $this->settings->execute(new SettingsChange(
                $replacement->set,
                $replacement->manifest ?? (array) ($draft->payload['manifest'] ?? []),
                $replacement->author,
                $summary,
                $basedOn,
                $replacement->mode,
            )),
        };

        if ($result->ok()) {
            $this->reject->execute(new ReviewDecision(
                $replacement->set,
                (string) $draft->id,
                $replacement->author,
                $result->revision?->id !== null
                    ? "Replaced by revision #{$result->revision->id}, edited in the admin UI."
                    : 'Production already has this change.',
            ));
        }

        return $result;
    }

    private function page(DraftReplacement $replacement, ?string $basedOn, string $summary): WriteResult
    {
        $draft = $replacement->draft;

        if ($draft->operation !== RevisionOperation::Write->value || $draft->ref === null) {
            return WriteResult::rejected(new ValidationReport, 'Only a change to a page\'s content can be edited. Publish or reject this move or deletion instead.');
        }

        $frontmatter = $replacement->frontmatter ?? (array) ($draft->payload['frontmatter'] ?? []);
        $body = $replacement->body ?? (string) ($draft->payload['body'] ?? '');

        return $this->pages->execute(new PageDraft(
            page: Page::make($draft->ref, new Frontmatter($frontmatter), $body),
            author: $replacement->author,
            channel: Channel::AdminUi,
            summary: $summary,
            basedOn: $basedOn,
            mode: $replacement->mode,
        ));
    }

    private function navigation(DraftReplacement $replacement, ?string $basedOn, string $summary): WriteResult
    {
        $draft = $replacement->draft;
        [$version, $locale] = array_pad(explode('/', $draft->subject, 2), 2, '');
        $tree = $replacement->tree ?? (array) ($draft->payload['tree'] ?? []);

        return $this->navigation->execute(new NavigationDraft(
            set: $replacement->set,
            version: $version,
            locale: $locale,
            author: $replacement->author,
            channel: Channel::AdminUi,
            tree: NavigationTree::fromJson($tree),
            summary: $summary,
            basedOn: $basedOn,
            mode: $replacement->mode,
        ));
    }

    private function summary(Revision $draft): string
    {
        $what = $draft->summary !== null && $draft->summary !== '' ? ": {$draft->summary}" : '';

        return $draft->is(RevisionStatus::Conflict)
            ? "Merge of conflict #{$draft->id}".($draft->migration !== null ? " ({$draft->migration})" : '')
            : "Edit of draft #{$draft->id}{$what}";
    }
}
