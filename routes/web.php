<?php

use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('ready', ReadinessController::class)->name('readiness');
Route::post('demo-login', DemoLoginController::class)->middleware('throttle:20,1')->name('demo.login');
Route::livewire('doctors', 'pages::doctors.index')->name('doctors.index');
Route::livewire('doctors/{doctor}', 'pages::doctors.show')->name('doctors.show');

Route::middleware(['auth', 'active'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::view('admin', 'dashboard')
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::livewire('admin/doctors', 'pages::admin.doctors')
        ->middleware('role:admin')
        ->name('admin.doctors');

    Route::livewire('admin/analytics', 'pages::admin.analytics')
        ->middleware('role:admin')
        ->name('admin.analytics');

    Route::middleware('role:patient')->prefix('patient')->name('patient.')->group(function () {
        Route::livewire('dashboard', 'pages::patient.dashboard')->name('dashboard');
        Route::livewire('doctors/{doctor}/book', 'pages::patient.book')->name('book');
        Route::livewire('appointments', 'pages::patient.appointments')->name('appointments.index');
        Route::livewire('appointments/{appointment}', 'pages::patient.appointment')->name('appointments.show');
        Route::livewire('appointments/{appointment}/confirmation', 'pages::patient.confirmation')->name('appointments.confirmation');
    });

    Route::middleware('role:receptionist')->prefix('receptionist')->name('receptionist.')->group(function () {
        Route::livewire('dashboard', 'pages::receptionist.dashboard')->name('dashboard');
        Route::livewire('pending', 'pages::receptionist.pending')->name('pending');
        Route::livewire('appointments', 'pages::receptionist.appointments')->name('appointments.index');
        Route::livewire('history', 'pages::receptionist.appointments')->name('history');
        Route::livewire('calendar', 'pages::receptionist.calendar')->name('calendar');
        Route::livewire('appointments/{appointment}', 'pages::receptionist.appointment')->name('appointments.show');
    });
});

require __DIR__.'/settings.php';
