<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use Illuminate\Http\Request;
use BinaryTorch\LaRecipe\LaRecipe;

class ScriptController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        return response(
            file_get_contents(LaRecipe::allScripts()[$request->script] ?? abort(404)),
            200,
            ['Content-Type' => 'application/javascript']
        );
    }
}
