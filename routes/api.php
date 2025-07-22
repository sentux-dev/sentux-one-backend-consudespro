<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\User\Profile\MfaController;
use App\Http\Controllers\Api\User\Profile\PersonalDataController;
use App\Http\Controllers\Api\User\Profile\PreferencesController;
use App\Http\Controllers\Api\User\Profile\SecurityDataController;
use App\Http\Controllers\Api\User\SessionController;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\Api\CRM\ContactController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/verify-email-login', [AuthController::class, 'verifyEmailLoginCode']);
Route::post('/auth/verify-app-login', [AuthController::class, 'verifyAppLoginCode']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);


    // User routes
    Route::prefix('user')->group(function () {
        Route::prefix('profile')->group(function () {
            // Información personal
            Route::get('personal-data', [PersonalDataController::class, 'show']);
            Route::put('personal-data', [PersonalDataController::class, 'update']);

            // Preferencias
            Route::get('preferences', [PreferencesController::class, 'show']);
            Route::put('preferences', [PreferencesController::class, 'update']);

            // Seguridad
            Route::get('security-data', [SecurityDataController::class, 'show']);
            Route::put('security-data', [SecurityDataController::class, 'update']);
             Route::put('security-data/password', [SecurityDataController::class, 'updatePassword']);

            //  MFA
            Route::prefix('mfa')->group(function () {
                Route::post('email/send', [MfaController::class, 'sendEmailCode']);
                Route::post('email/verify', [MfaController::class, 'verifyEmailCode']);
                Route::post('app/setup', [MfaController::class, 'setupTOTP']);
                Route::post('app/verify', [MfaController::class, 'verifyTOTP']);
                Route::delete('deactivate', [MfaController::class, 'deactivateMFA']);
            });
        });
        // Sesiones activas
        Route::get('sessions', [SessionController::class, 'index']);
        Route::delete('sessions/{id}', [SessionController::class, 'destroy']);
    });

    // Lista de usuarios
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Rutas de CRM
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts', [ContactController::class, 'store']);
    Route::get('/contacts/{contact}', [ContactController::class, 'show']);
    Route::put('/contacts/{contact}', [ContactController::class, 'update']);
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
    Route::patch('/contacts/{contact}/reactivate', [ContactController::class, 'reactivate']);

});
