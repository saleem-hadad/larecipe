<?php

use Illuminate\Support\Facades\Route;

// Built-in Search..
Route::get('/search-index/{version}', 'SearchController')->name('search');

// Documentation..
Route::get('/', 'DocumentationController@index')->name('index');
Route::get('/{version}/{page?}', 'DocumentationController@show')->where('page', '(.*)')->name('show');
