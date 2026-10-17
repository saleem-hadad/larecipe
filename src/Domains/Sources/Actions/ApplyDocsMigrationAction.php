<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Sources\Contracts\DocsMigrationRepository;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\DTOs\AppliedOperation;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationPlan;
use LaRecipe\Domains\Sources\DTOs\ManifestDraft;
use LaRecipe\Domains\Sources\DTOs\MigrationOutcome;
use LaRecipe\Domains\Sources\DTOs\NavigationDraft;
use LaRecipe\Domains\Sources\DTOs\PageDeletion;
use LaRecipe\Domains\Sources\DTOs\PageDraft;
use LaRecipe\Domains\Sources\DTOs\PageMove;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\DocsMigrationOperationType as Type;
use LaRecipe\Domains\Sources\Enums\WriteOutcome;
use LaRecipe\Domains\Sources\Exceptions\DocsMigrationFailed;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Services\PageAddresses;
use LaRecipe\Domains\Sources\Services\SetLayout;

/**
 * Applies docs-migration operations through the same Actions every other
 * writer uses, as Actor::Migration with the migration's author and ref, on
 * the Migration channel: the pull request was the review, so changes publish.
 *
 * A basedOn mismatch never overwrites: the change lands as a conflict draft
 * holding both versions, and the run carries on with a warning. Invalid
 * content (an empty page, an address that is not in the set) fails the run,
 * so CI catches a broken migration before any deploy does.
 */
final class ApplyDocsMigrationAction
{
    public function __construct(
        private readonly SourceResolver $sources,
        private readonly PageAddresses $addresses,
        private readonly WritePageAction $write,
        private readonly MovePageAction $move,
        private readonly DeletePageAction $delete,
        private readonly UpdateNavigationAction $navigation,
        private readonly UpdateManifestAction $manifest,
        private readonly DocsMigrationRepository $repository,
    ) {}

    public function execute(DocsMigrationPlan $plan): MigrationOutcome
    {
        $applied = [];
        $warnings = [];

        foreach ($plan->operations as $operation) {
            try {
                $result = $this->apply($plan, $operation);
            } catch (InvalidContentChange $exception) {
                throw DocsMigrationFailed::because($plan->name, $exception->getMessage());
            }

            if ($result->outcome === WriteOutcome::Rejected) {
                $errors = implode(' ', array_map(fn ($issue) => $issue->message, $result->validation->errors()));

                throw DocsMigrationFailed::because($plan->name, trim("{$operation->type->value} [{$operation->subject()}]: {$result->message} {$errors}"));
            }

            $applied[] = AppliedOperation::fromResult($operation, $result);

            if ($result->outcome === WriteOutcome::Conflicted) {
                $warnings[] = "[{$operation->subject()}] {$result->message}";
            }
        }

        $this->repository->append($plan->name, $plan->set, $applied);

        return new MigrationOutcome($plan->name, $plan->set, $applied, $warnings);
    }

    private function apply(DocsMigrationPlan $plan, DocsMigrationOperation $operation): WriteResult
    {
        // Read per operation: an earlier updateManifest may have added the version a page lives in.
        $manifest = $this->sources->for($plan->set)->manifest();
        $ref = fn (string $address) => $this->addresses->parse($address, $plan->set, $manifest);

        return match ($operation->type) {
            Type::CreatePage, Type::UpdatePage => $this->write->execute(new PageDraft(
                Page::make($ref($operation->target), new Frontmatter($operation->frontmatter), (string) $operation->body),
                $plan->author,
                Channel::Migration,
                $plan->summary,
                $operation->basedOn,
                creating: $operation->type === Type::CreatePage,
                migration: $plan->name,
            )),
            Type::MovePage => $this->move->execute(new PageMove(
                $ref($operation->target), $ref((string) $operation->to), $plan->author, Channel::Migration, $plan->summary, $operation->basedOn, migration: $plan->name,
            )),
            Type::DeletePage => $this->delete->execute(new PageDeletion(
                $ref($operation->target), $plan->author, Channel::Migration, $plan->summary, $operation->basedOn, migration: $plan->name,
            )),
            Type::UpdateNavigation => $this->navigation->execute(new NavigationDraft(
                $plan->set,
                $operation->target !== '' ? $operation->target : SetLayout::defaultVersion($manifest),
                $operation->locale ?? SetLayout::defaultLocale($manifest),
                $plan->author,
                Channel::Migration,
                tree: $operation->tree !== null ? NavigationTree::fromJson($operation->tree) : null,
                patch: $operation->tree === null ? ($operation->patch ?? []) : null,
                summary: $plan->summary,
                basedOn: $operation->basedOn,
                migration: $plan->name,
            )),
            Type::UpdateManifest => $this->manifest->execute(new ManifestDraft(
                $plan->set, $operation->patch ?? [], $plan->author, Channel::Migration, $plan->summary, $operation->basedOn, migration: $plan->name,
            )),
        };
    }
}
