<?php

use App\Http\Controllers\PlatformCompanyController as C;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('platform')->group(function () {
    Route::post('companies', [C::class, 'store']);
    Route::get('companies/{organisation}', [C::class, 'show']);
    Route::patch('companies/{organisation}', [C::class, 'update']);
    Route::post('companies/{organisation}/members', [C::class, 'member']);
    Route::post('companies/{organisation}/invitations', [C::class, 'invite'])->middleware('throttle:10,1');
    Route::patch('companies/{organisation}/members/{member}', [C::class, 'role']);
    Route::delete('companies/{organisation}/members/{member}', [C::class, 'remove']);
    Route::post('companies/{organisation}/transfer', [C::class, 'transfer']);
    Route::get('users', [C::class, 'users']);
    Route::patch('users/{user}/status', [C::class, 'userStatus']);
});
