<?php

use App\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('ready', ReadinessController::class)->name('readiness');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::view('admin', 'dashboard')
        ->middleware('role:administrator')
        ->name('admin.dashboard');

    Route::livewire('clinics', 'pages::clinics.index')->name('clinics.index');
    Route::livewire('clinics/{clinic}/scheduling', 'pages::clinics.scheduling')->name('clinics.scheduling');
    Route::livewire('clinics/{clinic}/appointments', 'pages::clinics.appointments')->name('clinics.appointments');
});

require __DIR__.'/settings.php';
