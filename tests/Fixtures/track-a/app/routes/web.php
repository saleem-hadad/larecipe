<?php

// #region orders
Route::middleware('auth')->post('/orders', [OrderController::class, 'store'])->name('orders.store');
// #endregion
