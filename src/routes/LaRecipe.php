<?php

Route::get(config('larecipe.docs.route'), function () {
    return 'okay';
})->name('docs.index');