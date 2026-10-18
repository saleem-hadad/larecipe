<?php

namespace LaRecipe\Domains\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use LaRecipe\Domains\Admin\Laravel\AdminResponses;
use LaRecipe\Domains\Admin\Queries\PendingRevisionsQuery;
use LaRecipe\Domains\Admin\Requests\DeletePageRequest;
use LaRecipe\Domains\Admin\Requests\MovePageRequest;
use LaRecipe\Domains\Admin\Requests\PageRequest;
use LaRecipe\Domains\Admin\Requests\SavePageRequest;
use LaRecipe\Domains\Admin\Support\AdminJson;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Sources\Actions\DeletePageAction;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\MovePageAction;
use LaRecipe\Domains\Sources\Actions\WritePageAction;
use LaRecipe\Domains\Sources\DTOs\PageDeletion;
use LaRecipe\Domains\Sources\DTOs\PageDraft;
use LaRecipe\Domains\Sources\DTOs\PageMove;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\Channel;

/**
 * One page in the editor: read it, create it, save it, move it, delete it.
 *
 * Every change goes through the Sources write Actions with the AdminUi
 * channel and the signed-in person as author, so it is validated, revisioned
 * and attributed exactly like an agent's or a docs migration's change. An
 * update sends back the hash it read (based_on): if the page changed in the
 * meantime, nothing is overwritten and the editor is told (409).
 */
final class PageController
{
    public function __construct(
        private readonly GetPageAction $pages,
        private readonly WritePageAction $write,
        private readonly MovePageAction $move,
        private readonly DeletePageAction $delete,
        private readonly PendingRevisionsQuery $pending,
        private readonly DocsUrls $urls,
        private readonly AdminResponses $responses,
    ) {}

    public function show(PageRequest $request): JsonResponse
    {
        $ref = $request->ref();
        $page = $this->pages->execute($ref);

        if ($page === null) {
            return $this->responses->message("There is no page at [{$ref->path}] in {$ref->version}/{$ref->locale}.", 404);
        }

        return new JsonResponse([
            'page' => AdminJson::page($page, $this->urls->page($request->docSet(), $ref)),
            'pending' => array_map(fn (Revision $revision) => AdminJson::revision($revision), $this->pending->forPage($ref)),
        ]);
    }

    public function store(SavePageRequest $request): JsonResponse
    {
        return $this->responses->guard(fn () => $this->responses->write($this->write->execute(new PageDraft(
            page: Page::make($request->ref(), $request->frontmatter(), $request->body()),
            author: $request->author(),
            channel: Channel::AdminUi,
            summary: $request->summary() ?? 'Create '.$request->ref()->path,
            creating: true,
            mode: $request->mode(),
        )), created: true));
    }

    public function update(SavePageRequest $request): JsonResponse
    {
        return $this->responses->guard(fn () => $this->responses->write($this->write->execute(new PageDraft(
            page: Page::make($request->ref(), $request->frontmatter(), $request->body()),
            author: $request->author(),
            channel: Channel::AdminUi,
            summary: $request->summary(),
            basedOn: $request->input('based_on'),
            mode: $request->mode(),
        ))));
    }

    public function move(MovePageRequest $request): JsonResponse
    {
        $from = $request->ref();

        return $this->responses->guard(fn () => $this->responses->write($this->move->execute(new PageMove(
            from: $from,
            to: $from->withPath((string) $request->input('to')),
            author: $request->author(),
            channel: Channel::AdminUi,
            summary: $request->summary() ?? "Move {$from->path} to {$request->input('to')}",
            basedOn: $request->input('based_on'),
            mode: $request->mode(),
        ))));
    }

    public function destroy(DeletePageRequest $request): JsonResponse
    {
        $ref = $request->ref();

        return $this->responses->guard(fn () => $this->responses->write($this->delete->execute(new PageDeletion(
            ref: $ref,
            author: $request->author(),
            channel: Channel::AdminUi,
            summary: $request->summary() ?? "Delete {$ref->path}",
            basedOn: $request->input('based_on'),
            mode: $request->mode(),
        ))));
    }
}
