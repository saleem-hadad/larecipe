<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use BinaryTorch\LaRecipe\Contracts\DocumentFinder;
use BinaryTorch\LaRecipe\Contracts\RequestPathParser;

class DocumentationController extends Controller
{
    public function index(RequestPathParser $requestPathParser)
    {
        $landingPath = $requestPathParser->getDefaultLandingPath();

        return redirect()->route('larecipe.show', ['path' => $landingPath]);
    }

    public function show($path, DocumentFinder $documentFinder)
    {
        $documentationResponse = $documentFinder->find($path);

        $this->ensureSuccessResponse($documentationResponse->document);

        $this->authorizeShow($documentationResponse->document);

        return response()->view('larecipe::docs', $documentationResponse->toArray());
    }

    private function ensureSuccessResponse($document)
    {
        abort_unless($document->hasContent(), 404);
    }

    private function authorizeShow($document)
    {
        if (Gate::has('viewLarecipe')) {
            $this->authorize('viewLarecipe', $document);
        }
    }
}
