<?php

namespace LaRecipe\Domains\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use LaRecipe\Domains\Admin\Actions\BuildPageTreeAction;
use LaRecipe\Domains\Admin\Laravel\AdminResponses;
use LaRecipe\Domains\Admin\Requests\ScopeRequest;
use LaRecipe\Domains\Admin\Requests\UpdateNavigationRequest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Sources\Actions\UpdateNavigationAction;
use LaRecipe\Domains\Sources\DTOs\NavigationDraft;
use LaRecipe\Domains\Sources\Enums\Channel;

/**
 * The page tree and the navigation behind it. Reordering, grouping and
 * moving pages in the tree saves a whole navigation.json; creating or
 * deleting a page sends a small patch instead. Both go through
 * UpdateNavigationAction, so a stale tree is never written over a newer one.
 */
final class NavigationController
{
    public function __construct(
        private readonly BuildPageTreeAction $tree,
        private readonly UpdateNavigationAction $navigation,
        private readonly AdminResponses $responses,
    ) {}

    public function tree(ScopeRequest $request): JsonResponse
    {
        return new JsonResponse($this->tree->execute($request->docSet()->key, $request->version(), $request->locale()));
    }

    public function update(UpdateNavigationRequest $request): JsonResponse
    {
        $tree = $request->input('tree');
        $patch = $request->input('patch');

        return $this->responses->guard(fn () => $this->responses->write($this->navigation->execute(new NavigationDraft(
            set: $request->docSet()->key,
            version: $request->version(),
            locale: $request->locale(),
            author: $request->author(),
            channel: Channel::AdminUi,
            tree: is_array($tree) ? NavigationTree::fromJson($tree) : null,
            patch: is_array($tree) ? null : array_values((array) $patch),
            summary: $request->summary() ?? 'Update the navigation',
            basedOn: $request->input('based_on'),
            mode: $request->mode(),
        ))));
    }
}
