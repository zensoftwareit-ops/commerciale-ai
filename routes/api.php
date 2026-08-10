<?php

use App\Http\Controllers\SimpleInboundLeadController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/inbound/leads/{token}', SimpleInboundLeadController::class)->middleware('throttle:60,1');
