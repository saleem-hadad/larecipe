<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;
use BinaryTorch\LaRecipe\Contracts\IDocumentationService;

class DocumentationController extends Controller
{
    public function index(GetDocumentRequest $getDocumentRequest)
    {
        $landingPath = $getDocumentRequest->getLandingPath();

        return redirect()->route('larecipe.show', ['path' => $landingPath]);
    }

    public function show($path, GetDocumentRequest $getDocumentRequest, IDocumentationService $documentationService)
    {
        $getDocumentRequest->parse($path);

        $documentationResponse = $documentationService->get($getDocumentRequest);

        $this->ensureSuccessResponse($documentationResponse);

        $this->authorizeShow($documentationResponse->document);

        return response()->view('larecipe::docs', $documentationResponse->toArray());
    }

    private function ensureSuccessResponse($documentationResponse)
    {
        $hasContent = $documentationResponse->document != null && $documentationResponse->sidebar != null;
        abort_unless($hasContent, 404);
    }

    private function authorizeShow($document)
    {
        if (Gate::has('viewLarecipe')) {
            $this->authorize('viewLarecipe', $document);
        }
    }
}
