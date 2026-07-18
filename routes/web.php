<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AvailableProviderAdaptersController;
use App\Http\Controllers\Admin\ProviderManagementController;
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

    Route::get('/providers', [ProviderManagementController::class, 'index'])->name('providers.index');
    Route::get('/providers/available-adapters', AvailableProviderAdaptersController::class)->name('providers.available-adapters');
    Route::post('/providers', [ProviderManagementController::class, 'store'])->name('providers.store');
    Route::put('/providers/{provider}', [ProviderManagementController::class, 'update'])->name('providers.update');
    Route::patch('/providers/{provider}/toggle', [ProviderManagementController::class, 'toggle'])->name('providers.toggle');
    Route::post('/providers/{provider}/credentials', [ProviderManagementController::class, 'rotateCredential'])->name('providers.credentials.rotate');
    Route::get('/providers/{provider}/http-adapter', [ProviderManagementController::class, 'configureHttpAdapter'])->name('providers.http-adapter.configure');
    Route::post('/providers/{provider}/http-adapter/test', [ProviderManagementController::class, 'testHttpAdapter'])->name('providers.http-adapter.test');
    Route::post('/providers/{provider}/http-adapter', [ProviderManagementController::class, 'saveHttpAdapter'])->name('providers.http-adapter.save');
    Route::delete('/providers/{provider}/http-adapter/{endpoint}', [ProviderManagementController::class, 'destroyHttpAdapter'])->name('providers.http-adapter.destroy');
    Route::post('/providers/{provider}/contract-fields', [ProviderManagementController::class, 'storeContractField'])->name('providers.contract-fields.store');
    Route::get('/providers/{provider}/contract-fields/{fieldKey}', fn (int $provider, string $fieldKey) => redirect()->route('admin.providers.http-adapter.configure', $provider))->name('providers.contract-fields.show');
    Route::put('/providers/{provider}/contract-fields/{fieldKey}', [ProviderManagementController::class, 'updateContractField'])->name('providers.contract-fields.update');

    Route::get('/manage-season', [SeasonManagementController::class, 'index'])->name('seasons.index');
    Route::post('/manage-season/analyze', [SeasonManagementController::class, 'analyze'])->name('seasons.analyze');
    Route::post('/manage-season/apply', [SeasonManagementController::class, 'apply'])->name('seasons.apply');
});
