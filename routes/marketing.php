<?php

use App\Http\Controllers\AudienceController as A;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController as C;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\ImportController as I;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('organisations/{organisation}')->group(function () {
    Route::get('contacts', [C::class, 'index']);
    Route::post('contacts', [C::class, 'store']);
    Route::post('contacts/bulk', [C::class, 'bulk']);
    Route::get('contacts/{contact}', [C::class, 'show']);
    Route::patch('contacts/{contact}', [C::class, 'update']);
    Route::post('contacts/{contact}/eligibility', [C::class, 'eligibility']);
    Route::post('contacts/{contact}/suppress', [C::class, 'suppress']);
    Route::get('audiences', [A::class, 'index']);
    Route::post('lists', [A::class, 'listStore']);
    Route::get('lists/{list}', [A::class, 'show']);
    Route::patch('lists/{list}', [A::class, 'listUpdate']);
    Route::post('lists/{list}/members', [A::class, 'membership']);
    Route::post('segments', [A::class, 'segment']);
    Route::patch('segments/{segment}', [A::class, 'segment']);
    Route::get('segments/{segment}/preview', [A::class, 'preview']);
    Route::get('imports', [I::class, 'index']);
    Route::post('imports', [I::class, 'upload'])->middleware('throttle:10,1');
    Route::get('imports/{import}', [I::class, 'show']);
    Route::post('imports/{import}/start', [I::class, 'start']);
    Route::get('imports/{import}/errors', [I::class, 'errors']);
});
Route::middleware(['auth', 'verified'])->prefix('organisations/{organisation}')->group(function () {
    $c = ContentController::class;
    Route::get('identities', [$c, 'identities']);
    Route::post('identities', [$c, 'identitySave']);
    Route::patch('identities/{identity}', [$c, 'identitySave']);
    Route::get('templates', [$c, 'templates']);
    Route::post('templates', [$c, 'templateSave']);
    Route::post('templates/starter', [$c, 'starter']);
    Route::get('templates/{template}', [$c, 'templateShow']);
    Route::patch('templates/{template}', [$c, 'templateSave']);
    Route::post('templates/{template}/duplicate', [$c, 'duplicate']);
    Route::get('templates/{template}/preview', [$c, 'preview']);
});
Route::get('/unsubscribe/{token}', [UnsubscribeController::class, 'show'])->middleware(['signed:relative', 'throttle:30,1'])->name('unsubscribe.show');
Route::post('/unsubscribe/{token}', [UnsubscribeController::class, 'store'])->middleware(['signed:relative', 'throttle:30,1']);
Route::middleware(['auth', 'verified'])->prefix('organisations/{organisation}')->group(function () {
    $c = CampaignController::class;
    Route::get('campaigns', [$c, 'index']);
    Route::get('campaigns/create', [$c, 'create']);
    Route::post('campaigns', [$c, 'save']);
    Route::get('campaigns/{campaign}', [$c, 'show']);
    Route::get('campaigns/{campaign}/edit', [$c, 'edit']);
    Route::patch('campaigns/{campaign}', [$c, 'save']);
    Route::post('campaigns/{campaign}/ready', [$c, 'ready']);
    Route::post('campaigns/{campaign}/schedule', [$c, 'schedule']);
    Route::post('campaigns/{campaign}/cancel', [$c, 'cancel']);
    Route::post('campaigns/{campaign}/duplicate', [$c, 'duplicate']);
    Route::get('campaigns/{campaign}/preview', [$c, 'preview']);
    Route::get('campaigns/{campaign}/recipients/{recipient}/unsubscribe',[$c, 'unsubscribe']);
    Route::get('audience-preview',[$c, 'audience']);
    Route::get('reports',[$c, 'reporting']);
});
