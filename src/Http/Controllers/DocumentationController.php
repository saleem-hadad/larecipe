<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

class DocumentationController extends Controller
{
    /**
     * Redirect the index page of docs to the current version.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function index()
    {
        return redirect()->route('docs.show', config('larecipe.versions.default'));
    }
    
    public function show($version, $page = null)
    {
        return $version;
    }
}
