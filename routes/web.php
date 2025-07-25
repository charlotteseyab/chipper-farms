<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/admin/dashboard', 'admin.dashboard')->name('admin.dashboard');
    Route::view('/manager/dashboard', 'manager.dashboard')->name('manager.dashboard');
    Route::view('/superuser/dashboard', 'superuser.dashboard')->name('superuser.dashboard');
    Route::resource('breeding-stocks', 'App\Http\Controllers\BreedingStockController');
});
