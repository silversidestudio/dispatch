<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/organisations', [OrganisationController::class, 'index'])->name('organisations.index');
    Route::post('/organisations', [OrganisationController::class, 'store'])->middleware('throttle:20,1')->name('organisations.store');
    Route::post('/organisations/{organisation}/switch', [OrganisationController::class, 'switch'])->name('organisations.switch');
    Route::get('/organisations/{organisation}', [OrganisationController::class, 'show'])->name('organisations.show');
    Route::patch('/organisations/{organisation}', [OrganisationController::class, 'update'])->name('organisations.update');
    Route::post('/organisations/{organisation}/invitations', [OrganisationController::class, 'invite'])->middleware('throttle:10,1')->name('organisations.invite');
    Route::patch('/organisations/{organisation}/members/{member}', [OrganisationController::class, 'role'])->name('organisations.role');
    Route::get('/invitations/{token}', fn (string $token) => Inertia::render('accept-invitation', ['token' => $token]))->where('token', '[A-Za-z0-9]{64}');
    Route::post('/invitations/{token}', [OrganisationController::class, 'accept'])->middleware('throttle:10,1');
    Route::get('/platform', [PlatformController::class, 'index'])->name('platform.index');
    Route::patch('/platform/organisations/{organisation}', [PlatformController::class, 'status'])->name('platform.status');
});
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
