<?php

use Illuminate\Support\Facades\Route;

// Built-in Search..
Route::get('/search-index/{version}', 'SearchController')->name('search');

// Styles & Scripts..
Route::get('/styles/{style}', 'StyleController')->name('styles');
Route::get('/scripts/{script}', 'ScriptController')->name('scripts');

// Documentation..
Route::get('/', 'DocumentationController@index')->name('index');

Route::get('/{product}/{version}/{page?}', 'DocumentationController@showProduct')->where('version','[0-9].[0-9]')->where('product','^[0-9a-zA-Z/ -]+$')->name('show.product');

Route::get('/{version}/{page?}', 'DocumentationController@show')->where('page', '(.*)')->name('show');

