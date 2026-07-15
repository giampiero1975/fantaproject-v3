<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\SeasonManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->hasRole('admin')
        ? redirect()->route('admin.dashboard')
        : redirect()->route('dashboard');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'admin',
])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/manage-season', [SeasonManagementController::class, 'index'])->name('seasons.index');
    Route::post('/manage-season/analyze', [SeasonManagementController::class, 'analyze'])->name('seasons.analyze');
    Route::post('/manage-season/apply', [SeasonManagementController::class, 'apply'])->name('seasons.apply');
});
