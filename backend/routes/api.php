<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CareController;
use App\Http\Middleware\EnsureActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', fn () => ['status' => 'ok', 'service' => 'CareCircle', 'demo' => app()->environment('local')]);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:carecircle-login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:carecircle-register');
    Route::middleware(['auth:sanctum', EnsureActive::class, 'throttle:120,1'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'update']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('meta', [CareController::class, 'meta']);
        Route::get('beneficiaries', [CareController::class, 'beneficiaries']);
        Route::post('beneficiaries', [CareController::class, 'createBeneficiary']);
        Route::patch('beneficiaries/{beneficiary}', [CareController::class, 'updateBeneficiary']);
        Route::get('family-authorizations', [CareController::class, 'authorizations']);
        Route::post('family-authorizations', [CareController::class, 'inviteFamily']);
        Route::post('family-authorizations/{authorization}/revoke', [CareController::class, 'revokeFamily']);
        Route::get('volunteer-profile', [CareController::class, 'volunteer']);
        Route::put('volunteer-profile', [CareController::class, 'applyVolunteer']);
        Route::get('requests', [CareController::class, 'index']);
        Route::post('requests', [CareController::class, 'store']);
        Route::get('requests/{helpRequest}', [CareController::class, 'show']);
        Route::post('requests/{helpRequest}/cancel', [CareController::class, 'cancel']);
        Route::get('offers', [CareController::class, 'offers']);
        Route::post('offers/{offer}/accept', [CareController::class, 'accept']);
        Route::post('offers/{offer}/decline', [CareController::class, 'decline']);
        Route::post('requests/{helpRequest}/start', [CareController::class, 'start']);
        Route::post('requests/{helpRequest}/arrive', [CareController::class, 'arrive']);
        Route::post('requests/{helpRequest}/handover-code', [CareController::class, 'code'])->middleware('throttle:handover-issue');
        Route::post('requests/{helpRequest}/confirm', [CareController::class, 'confirm'])->middleware('throttle:handover-confirm');
        Route::post('requests/{helpRequest}/report', [CareController::class, 'report']);
        Route::get('inbox', [CareController::class, 'inbox']);
        Route::post('inbox/{message}/read', [CareController::class, 'read']);
    });
});
