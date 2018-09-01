<?php

Route::group([
    'prefix'     => config('larecipe.docs.route'),
    'namespace'  => 'BinaryTorch\LaRecipe\Http\Controllers',
    'as'         => 'larecipe.',
], function () {
    Route::get('/', 'DocumentationController@index')->name('index');
    Route::get('/{version}/{page?}', 'DocumentationController@show')->name('show');
});
