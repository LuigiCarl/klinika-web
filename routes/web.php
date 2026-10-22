<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::view('admin', 'dashboard')
        ->middleware('role:administrator')
        ->name('admin.dashboard');
});

require __DIR__.'/settings.php';
