<?php

use App\Http\Controllers\PinitImportController;
use App\Livewire\Repartidor\Home as RepartidorHome;
use App\Livewire\Repartidor\Login as RepartidorLogin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'web'])->group(function () {
    Route::post('/admin/pinit-import/upload', [PinitImportController::class, 'store'])->name('pinit-import.store');
});

Route::prefix('repartidor')->name('repartidor.')->group(function () {
    Route::middleware('guest:repartidor')->group(function () {
        Route::get('/login', RepartidorLogin::class)->name('login');
    });

    Route::middleware('auth:repartidor')->group(function () {
        Route::get('/', RepartidorHome::class)->name('home');
    });
});
