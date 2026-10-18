<?php

namespace LaRecipe\Domains\Sources\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Sources\Actions\WritePageAction;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\PageDraft;
use LaRecipe\Domains\Sources\Services\ContentHashes;
use LaRecipe\Domains\Sources\Services\PageAddresses;

#[Name('write_page')]
#[Title('Write a docs page')]
#[Description('Create or replace one docs page: frontmatter and Markdown body. Read the page first with read_page and pass the content_hash it returns as based_on, so you never overwrite a newer edit (leave based_on out for a new page). On a repository set the file changes in the working tree; on a database set the change is applied to the local database and written into the branch\'s docs migration (commit it with the code). Returns the revision and the validation result; fix every validation error it reports.')]
#[IsDestructive]
final class WritePageTool extends AuthorTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('The page path inside its version, without .md, for example "billing/exports". "index" is the landing page.')->required(),
            'frontmatter' => $schema->object()->description('Page-level frontmatter: title, description, sources (globs of the code this page documents), order, tags, ... Never configuration or environment values.'),
            'body' => $schema->string()->description('The Markdown body, without the frontmatter block.')->required(),
            ...$this->scopeArguments($schema),
            ...$this->changeArguments($schema),
            ...$this->setArgument($schema),
        ];
    }

    public function handle(Request $request, AuthorSession $session, WritePageAction $write, PageAddresses $addresses): ResponseFactory
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:512'],
            'body' => ['present', 'string'],
            'frontmatter' => ['nullable', 'array'],
            'version' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:16'],
            'summary' => ['nullable', 'string', 'max:500'],
            'based_on' => ['nullable', 'string', 'max:80'],
            'set' => ['nullable', 'string', 'max:64'],
        ]);

        $set = $session->set($request);

        if (($denial = $session->denial($set)) !== null) {
            return $this->refuse($denial);
        }

        return $this->guarded(function () use ($data, $set, $session, $write, $addresses, $request) {
            $ref = $this->ref($set, $session, $data['path'], $data['version'] ?? null, $data['locale'] ?? null);
            $frontmatter = (array) ($data['frontmatter'] ?? []);
            $author = $session->author($request);

            $result = $write->execute(new PageDraft(
                Page::make($ref, new Frontmatter($frontmatter), $data['body']),
                $author,
                $session->channel(),
                $data['summary'] ?? null,
                $data['based_on'] ?? null,
            ));

            $address = $addresses->format($ref, $session->manifest($set));
            $basedOn = $result->revision?->basedOnHash;
            $operation = $basedOn === null || $basedOn === ContentHashes::ABSENT
                ? DocsMigrationOperation::createPage($address, $frontmatter, $data['body'])
                : DocsMigrationOperation::updatePage($address, $basedOn, $frontmatter, $data['body']);

            return $this->respond($result, $session->recordMigration($set, $operation, $result, $author, $data['summary'] ?? null));
        });
    }
}
