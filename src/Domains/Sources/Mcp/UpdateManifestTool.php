<?php

namespace LaRecipe\Domains\Sources\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use LaRecipe\Domains\Sources\Actions\UpdateManifestAction;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\ManifestDraft;

#[Name('update_manifest')]
#[Title('Update the doc set manifest')]
#[Description('Change the set\'s larecipe.json (name, description, theme, versions, locales, navbar, redirects, agent description) with a JSON merge patch: keys replace, null removes a key, objects merge, lists are replaced whole. Presentation and structure only: routes, drivers, AI settings and secrets live in config/larecipe.php and never here, and so do a database set\'s analytics integrations.')]
#[IsDestructive]
final class UpdateManifestTool extends AuthorTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'patch' => $schema->object()->description('A JSON merge patch (RFC 7386) for larecipe.json.')->required(),
            ...$this->changeArguments($schema),
            ...$this->setArgument($schema),
        ];
    }

    public function handle(Request $request, AuthorSession $session, UpdateManifestAction $manifest): ResponseFactory
    {
        $data = $request->validate([
            'patch' => ['required', 'array'],
            'summary' => ['nullable', 'string', 'max:500'],
            'based_on' => ['nullable', 'string', 'max:80'],
            'set' => ['nullable', 'string', 'max:64'],
        ]);

        $set = $session->set($request);

        if (($denial = $session->denial($set)) !== null) {
            return $this->refuse($denial);
        }

        return $this->guarded(function () use ($data, $set, $session, $manifest, $request) {
            $author = $session->author($request);
            $result = $manifest->execute(new ManifestDraft($set->key, $data['patch'], $author, $session->channel(), $data['summary'] ?? null, $data['based_on'] ?? null));
            $operation = DocsMigrationOperation::updateManifest($result->revision?->basedOnHash, $data['patch']);

            return $this->respond($result, $session->recordMigration($set, $operation, $result, $author, $data['summary'] ?? null));
        });
    }
}
