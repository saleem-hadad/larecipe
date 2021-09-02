<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use BinaryTorch\LaRecipe\Contracts\DocumentRepository;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest;

class DocumentationController extends Controller
{
    public function index(GetDocumentRequest $getDocumentRequest)
    {
        $landingPath = $getDocumentRequest->getLandingPath();

        return redirect()->route('larecipe.show', ['path' => $landingPath]);
    }

    public function show($path, GetDocumentRequest $getDocumentRequest, DocumentRepository $documentRepository)
    {
        $getDocumentRequest->parse($path);

        $documentationResponse = $documentRepository->find($getDocumentRequest);

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
