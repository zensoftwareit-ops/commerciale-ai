<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InboundSourceController;
use App\Http\Controllers\KnowledgeDocumentController;
use App\Http\Controllers\LeadAnalysisController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\PasswordController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/leads');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->name('password.update');
});

Route::middleware(['auth', 'tenant'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/create', [LeadController::class, 'create'])->middleware('role:owner,sales')->name('leads.create');
    Route::post('/leads', [LeadController::class, 'store'])->middleware('role:owner,sales')->name('leads.store');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::patch('/leads/{lead}', [LeadController::class, 'update'])->middleware('role:owner,sales')->name('leads.update');
    Route::post('/leads/{lead}/analyze', [LeadAnalysisController::class, 'store'])->middleware('role:owner,sales')->name('leads.analyze');
    Route::patch('/leads/{lead}/analyses/{analysis}', [LeadAnalysisController::class, 'update'])->middleware('role:owner,sales')->name('analyses.update');
    Route::get('/settings/organization', [OrganizationSettingsController::class, 'edit'])->middleware('role:owner')->name('settings.organization');
    Route::put('/settings/organization', [OrganizationSettingsController::class, 'update'])->middleware('role:owner')->name('settings.organization.update');
    Route::get('/settings/sources', [InboundSourceController::class, 'index'])->middleware('role:owner')->name('settings.sources');
    Route::post('/settings/sources', [InboundSourceController::class, 'store'])->middleware('role:owner')->name('settings.sources.store');
    Route::put('/settings/sources/{source}', [InboundSourceController::class, 'update'])->middleware('role:owner')->name('settings.sources.update');
    Route::patch('/settings/sources/{source}/rotate-endpoint', [InboundSourceController::class, 'rotateEndpoint'])->middleware('role:owner')->name('settings.sources.rotate-endpoint');
    Route::patch('/settings/sources/{source}/rotate', [InboundSourceController::class, 'rotate'])->middleware('role:owner')->name('settings.sources.rotate');
    Route::get('/knowledge', [KnowledgeDocumentController::class, 'index'])->name('knowledge.index');
    Route::get('/knowledge/create', [KnowledgeDocumentController::class, 'create'])->middleware('role:owner')->name('knowledge.create');
    Route::post('/knowledge', [KnowledgeDocumentController::class, 'store'])->middleware('role:owner')->name('knowledge.store');
    Route::get('/knowledge/{document}/edit', [KnowledgeDocumentController::class, 'edit'])->middleware('role:owner')->name('knowledge.edit');
    Route::put('/knowledge/{document}', [KnowledgeDocumentController::class, 'update'])->middleware('role:owner')->name('knowledge.update');
});
