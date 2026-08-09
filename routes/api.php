<?php

use App\Http\Controllers\InboundLeadController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/inbound/leads', InboundLeadController::class)->middleware('throttle:60,1');
