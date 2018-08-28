<?php

$groupSettings = [
    'prefix'     => config('larecipe.docs.route'),
    'namespace'  => 'BinaryTorch\LaRecipe\Http\Controllers',
    'as'         => 'docs.',
];

if (config('larecipe.settings.auth')) {
    $groupSettings['middleware'] = ['web', 'auth'];
}

Route::group($groupSettings, function () {
    Route::get('/', 'DocumentationController@index')->name('index');
    Route::get('/{version}/{page?}', 'DocumentationController@show')->name('show');
});
