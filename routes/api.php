<?php

use App\Http\Controllers\InboundLeadController;
use App\Http\Controllers\SimpleInboundLeadController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/inbound/leads', InboundLeadController::class)->middleware('throttle:60,1');
Route::post('/v1/inbound/leads/{token}', SimpleInboundLeadController::class)->middleware('throttle:60,1');
