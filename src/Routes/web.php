<?php

use Illuminate\Support\Facades\Route;
use Bagisto\Tamara\Controllers\TamaraController;

Route::group(['middleware' => ['web', 'theme', 'locale', 'currency']], function () {
    Route::get('tamara/init-session',[TamaraController::class, 'init'])->name('tamara.process');
    Route::any('tamara/callback',[TamaraController::class, 'callback'])->name('tamara.callback');
    Route::any('tamara/order-cancel',[TamaraController::class, 'cancel'])->name('tamara.cancel');
    Route::any('tamara/order-failed',[TamaraController::class, 'failed'])->name('tamara.failure');
    Route::any('tamara/order-success',[TamaraController::class, 'success'])->name('tamara.success');
    Route::any('tamara/webhook',[TamaraController::class, 'webhook'])->name('tamara.notify');
});