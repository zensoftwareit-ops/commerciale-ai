<?php

use App\Http\Controllers\SimpleInboundLeadController;
use App\Http\Controllers\Api\BillingPlanController;
use App\Http\Controllers\Api\BillingProvisionController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/inbound/leads/{token}', SimpleInboundLeadController::class)->middleware('throttle:60,1');

Route::middleware(['billing.selfservice', 'billing.client', 'throttle:120,1'])->prefix('/v1/billing')->group(function (): void {
    Route::get('/plans', BillingPlanController::class);
    Route::post('/provision', [BillingProvisionController::class, 'store']);
    Route::get('/accounts/{externalAccountId}/license', [BillingProvisionController::class, 'show']);
    Route::patch('/accounts/{externalAccountId}', [BillingProvisionController::class, 'updateAccount']);
});
