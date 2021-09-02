<?php

use Illuminate\Support\Facades\Route;
use BinaryTorch\LaRecipe\Http\Controllers\DocumentationController;

// Styles & Scripts..
Route::get('/styles/{style}', 'StyleController')->name('styles');
Route::get('/scripts/{script}', 'ScriptController')->name('scripts');

// Documentation..
Route::get('/', [DocumentationController::class, 'index'])->name('index');
Route::get('/{path?}', [DocumentationController::class, 'show'])->where('path', '(.*)')->name('show');
