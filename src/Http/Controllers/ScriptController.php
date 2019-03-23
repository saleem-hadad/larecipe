<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use Illuminate\Http\Request;
use BinaryTorch\LaRecipe\LaRecipe;

class ScriptController extends Controller
{
    /**
     *
     * @param  string $version
     * @return Response
     */
    public function __invoke(Request $request)
    {
        return response(
            file_get_contents(LaRecipe::allScripts()[$request->script]),
            200, ['Content-Type' => 'application/javascript']
        );
    }
}
