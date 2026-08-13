<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\InstagramWebhookController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login'])->name('api.login');

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('api.webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('api.webhooks.whatsapp.receive');

Route::get('/webhooks/instagram', [InstagramWebhookController::class, 'verify'])->name('api.webhooks.instagram.verify');
Route::post('/webhooks/instagram', [InstagramWebhookController::class, 'receive'])->name('api.webhooks.instagram.receive');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/reports', [ReportController::class, 'index'])->name('api.reports.index');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('api.reports.show');
    Route::put('/reports/{report}/status', [ReportController::class, 'updateStatus'])->name('api.reports.update-status');
    Route::post('/reports/{report}/assign', [ReportController::class, 'assign'])->name('api.reports.assign');
    Route::post('/device-tokens', [DeviceTokenController::class, 'store'])->name('api.device-tokens.store');
    Route::delete('/device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy'])->name('api.device-tokens.destroy');
});
