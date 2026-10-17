<?php

namespace LaRecipe\Domains\Agents\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\Mcp\ToolContext;
use LaRecipe\Domains\Agents\Services\OutlineBuilder;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageSummary;

/**
 * list_pages: the sidebar for agents. The navigation tree with page titles,
 * descriptions and paths, limited to what the caller may see.
 *
 * On the local server with more than one set and no set given, it lists the
 * sets first, so an agent learns what exists before it picks one.
 */
#[Name('list_pages')]
#[Title('List the docs pages')]
#[Description('List the docs as the navigation shows them: groups, then pages with their title, path and description. Pass group to list one group only. Read a page with read_page and its path.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class ListPagesTool extends Tool
{
    public function __construct(
        private readonly ToolContext $context,
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
        private readonly OutlineBuilder $outlines,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'version' => $schema->string()->description('The docs version. Defaults to the current version.'),
            'locale' => $schema->string()->description('The language, as a locale code. Defaults to the main language.'),
            'group' => $schema->string()->description('Only list the group with this label.'),
            ...$this->context->setArgument($schema),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($this->context->scope()->isLocal() && ! $request->get('set') && count($this->docs->sets()) > 1) {
            $this->context->called(null, 'list_pages', $request);

            return Response::structured([
                'sets' => array_map(fn (DocSet $set) => [
                    'set' => $set->key,
                    'name' => $set->manifest->name,
                    'description' => $set->manifest->description,
                    'url' => $this->links->base($set),
                    'versions' => $this->context->versions($set),
                    'default' => $set->key === $this->docs->defaultSet()->key,
                ], $this->docs->sets()),
                'hint' => 'This server serves several doc sets. Call list_pages again with set to see one set\'s pages.',
            ]);
        }

        $set = $this->context->set($request);

        if (is_string($set)) {
            $this->context->called(null, 'list_pages', $request, successful: false);

            return Response::error($set);
        }

        $scope = $this->context->scopeOf($set, $request->get('version'), $request->get('locale'));

        if (is_string($scope)) {
            $this->context->called($set, 'list_pages', $request, successful: false);

            return Response::error($scope);
        }

        $pages = $this->docs->pages($set, $scope['version'], $scope['locale']);
        $tree = [];

        foreach ($this->docs->navigation($set, $scope['version'], $scope['locale']) as $tab) {
            $items = $this->items($set, $tab['items'], $pages);

            if ($tab['href'] !== null) {
                $tree[] = ['type' => 'link', 'label' => (string) $tab['label'], 'href' => $tab['href']];
            } elseif ($tab['label'] !== null) {
                $tree[] = ['type' => 'tab', 'label' => $tab['label'], 'items' => $items];
            } else {
                array_push($tree, ...$items);
            }
        }

        $group = trim((string) $request->get('group', ''));

        if ($group !== '') {
            $match = $this->find($tree, $group);

            if ($match === null) {
                $this->context->called($set, 'list_pages', $request, successful: false);

                return Response::error("There is no group \"{$group}\". Groups: ".implode('; ', $this->groupLabels($tree)).'.');
            }

            $tree = [$match];
        }

        $this->context->called($set, 'list_pages', $request);

        return Response::structured([
            'set' => $set->key,
            'name' => $set->manifest->name,
            'version' => $scope['version'],
            'locale' => $scope['locale'],
            'versions' => $this->context->versions($set),
            'pages' => $tree,
            'other_pages' => $group === '' ? array_map(
                fn ($entry) => $entry->toArray(),
                $this->outlines->build($set, $scope['version'], $scope['locale'])->optional,
            ) : [],
        ]);
    }

    /**
     * @param  array<string, PageSummary>  $pages
     * @return list<array<string, mixed>>
     */
    private function items(DocSet $set, array $items, array $pages): array
    {
        $out = [];

        foreach ($items as $item) {
            $out[] = match ($item['type']) {
                'page' => array_filter([
                    'type' => 'page',
                    'title' => $item['title'],
                    'path' => $item['path'],
                    'description' => $pages[$item['path']]->description ?? null,
                    'url' => isset($pages[$item['path']]) ? $this->links->page($set, $pages[$item['path']]->ref, true) : null,
                ], fn ($value) => $value !== null),
                'group' => ['type' => 'group', 'label' => $item['label'], 'items' => $this->items($set, $item['items'], $pages)],
                default => ['type' => 'link', 'label' => $item['label'], 'href' => $item['href']],
            };
        }

        return $out;
    }

    private function find(array $items, string $label): ?array
    {
        foreach ($items as $item) {
            if (in_array($item['type'], ['group', 'tab'], true)) {
                if (mb_strtolower($item['label']) === mb_strtolower($label)) {
                    return $item;
                }

                if (($found = $this->find($item['items'], $label)) !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /** @return list<string> */
    private function groupLabels(array $items): array
    {
        $labels = [];

        foreach ($items as $item) {
            if (in_array($item['type'], ['group', 'tab'], true)) {
                $labels[] = $item['label'];
                array_push($labels, ...$this->groupLabels($item['items']));
            }
        }

        return $labels;
    }
}
