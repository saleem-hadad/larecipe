<?php

namespace SaleemHadad\LaRecipe\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use SaleemHadad\LaRecipe\Interfaces\GetDocumentRequest;
use SaleemHadad\LaRecipe\Interfaces\IDocumentationService;

class DocumentationController extends Controller
{
    public function index(GetDocumentRequest $getDocumentRequest)
    {
        $landingPath = $getDocumentRequest->getLandingPath();

        return redirect($landingPath);
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
