<?php

use App\Livewire\Repartidor\Home as RepartidorHome;
use App\Livewire\Repartidor\Login as RepartidorLogin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('repartidor')->name('repartidor.')->group(function () {
    Route::middleware('guest:repartidor')->group(function () {
        Route::get('/login', RepartidorLogin::class)->name('login');
    });

    Route::middleware('auth:repartidor')->group(function () {
        Route::get('/', RepartidorHome::class)->name('home');
    });
});
