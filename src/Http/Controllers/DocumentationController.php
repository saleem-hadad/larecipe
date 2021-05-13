<?php

namespace BinaryTorch\LaRecipe\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use BinaryTorch\LaRecipe\Contracts\DocumentFinder;

class DocumentationController extends Controller
{
//    public function __construct()
//        if (config('larecipe.settings.auth')) {
//            $this->middleware(['auth']);
//        }else{
//            if(config('larecipe.settings.middleware')){
//                $this->middleware(config('larecipe.settings.middleware'));
//            }
//        }

//if (Gate::has('viewLarecipe')) {
//$this->authorize('viewLarecipe', $documentation);
//}
//    }

    public function __invoke($path = '', DocumentFinder $documentFinder)
    {
        $document = $documentFinder->find($path);

        return response()->view('larecipe::docs', $document->toArray(), $document->hasContent() ? 200 : 404);
    }
}
