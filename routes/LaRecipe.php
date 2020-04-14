<?php

use Illuminate\Support\Facades\Route;

// Built-in Search..
Route::get('/search-index/{version}', ['uses' => 'SearchController@show', 'as' => 'search']);

// Styles & Scripts..
Route::get('/styles/{style}', ['uses' => 'StyleController@show', 'as' => 'styles']);
Route::get('/scripts/{script}', ['uses' => 'ScriptController@show', 'as' => 'scripts']);

// Documentation..
Route::get('/', ['uses' => 'DocumentationController@index', 'as' => 'index']);
Route::get('/{version}/{page?}', ['uses' => 'DocumentationController@show', 'as' => 'show'])->where('page', '(.*)');
