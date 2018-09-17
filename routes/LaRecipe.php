<?php

Route::group([
    'prefix'     => config('larecipe.docs.route'),
    'namespace'  => 'BinaryTorch\LaRecipe\Http\Controllers',
    'as'         => 'larecipe.',
    'middleware' => 'web'
], function () {
    Route::get('/', 'DocumentationController@index')->name('index');
    Route::get('/{version}/{page?}', 'DocumentationController@show')->where('page', '(.*)')->name('show');
});
