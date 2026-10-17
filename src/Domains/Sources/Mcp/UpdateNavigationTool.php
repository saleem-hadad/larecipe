<?php

namespace LaRecipe\Domains\Sources\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Sources\Actions\UpdateNavigationAction;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\NavigationDraft;
use LaRecipe\Domains\Sources\Services\SetLayout;

#[Name('update_navigation')]
#[Title('Update the docs navigation')]
#[Description('Change the navigation (navigation.json) of one version: send a whole tree, or a patch, a list of operations such as {"add": "billing/exports", "group": "Billing", "after": "billing/overview"}, {"move": "a", "group": "B"}, {"remove": "a"} or {"rename": "a", "to": "b"}. A version without navigation.json starts from the navigation derived from its folders.')]
#[IsDestructive]
final class UpdateNavigationTool extends AuthorTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->scopeArguments($schema),
            'tree' => $schema->object()->description('A whole navigation.json: {"groups": [...]} or {"tabs": [...]}.'),
            'patch' => $schema->array()->items($schema->object())->description('Operations applied in order: add, move, remove, rename.'),
            ...$this->changeArguments($schema),
            ...$this->setArgument($schema),
        ];
    }

    public function handle(Request $request, AuthorSession $session, UpdateNavigationAction $navigation): ResponseFactory
    {
        $data = $request->validate([
            'version' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:16'],
            'tree' => ['nullable', 'array', 'required_without:patch'],
            'patch' => ['nullable', 'array', 'required_without:tree'],
            'summary' => ['nullable', 'string', 'max:500'],
            'based_on' => ['nullable', 'string', 'max:80'],
            'set' => ['nullable', 'string', 'max:64'],
        ]);

        $set = $session->set($request);

        if (($denial = $session->denial($set)) !== null) {
            return $this->refuse($denial);
        }

        return $this->guarded(function () use ($data, $set, $session, $navigation, $request) {
            $manifest = $session->manifest($set);
            $version = $data['version'] ?? SetLayout::defaultVersion($manifest);
            $locale = $data['locale'] ?? SetLayout::defaultLocale($manifest);
            $author = $session->author($request);

            $result = $navigation->execute(new NavigationDraft(
                $set->key,
                $version,
                $locale,
                $author,
                $session->channel(),
                tree: isset($data['tree']) ? NavigationTree::fromJson($data['tree']) : null,
                patch: isset($data['tree']) ? null : array_values((array) $data['patch']),
                summary: $data['summary'] ?? null,
                basedOn: $data['based_on'] ?? null,
            ));

            // The migration carries the resulting tree, so a reviewer sees the whole navigation it leads to.
            $operation = is_array($result->revision?->payload['tree'] ?? null)
                ? DocsMigrationOperation::updateNavigation(
                    $manifest !== null && $manifest->isVersioned() ? $version : '',
                    $result->revision->basedOnHash,
                    $result->revision->payload['tree'],
                    locale: $locale !== SetLayout::defaultLocale($manifest) ? $locale : null,
                )
                : null;

            return $this->respond($result, $session->recordMigration($set, $operation, $result, $author, $data['summary'] ?? null));
        });
    }
}
