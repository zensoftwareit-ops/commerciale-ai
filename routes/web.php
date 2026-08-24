<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CommercialNotificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InboundSourceController;
use App\Http\Controllers\InboundEmailController;
use App\Http\Controllers\KnowledgeDocumentController;
use App\Http\Controllers\LeadAnalysisController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadReplyController;
use App\Http\Controllers\MailboxAccountController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\OrganizationSwitchController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PricingRuleController;
use App\Http\Controllers\Admin\LicensingDashboardController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\LicensePlanController as AdminLicensePlanController;
use App\Http\Controllers\Admin\LicenseController as AdminLicenseController;
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

Route::middleware('auth')->post('/logout', [AuthController::class, 'destroy'])->name('logout');
Route::middleware('auth')->post('/organizations/{organization}/switch', OrganizationSwitchController::class)->name('organizations.switch');
Route::middleware(['auth', 'superadmin'])->prefix('/admin')->name('admin.')->group(function (): void {
    Route::get('/licensing', LicensingDashboardController::class)->name('licensing');
    Route::get('/organizations', [AdminOrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/plans', [AdminLicensePlanController::class, 'store'])->name('plans.store');
    Route::put('/plans/{plan}', [AdminLicensePlanController::class, 'update'])->name('plans.update');
    Route::post('/licenses', [AdminLicenseController::class, 'store'])->name('licenses.store');
    Route::post('/licenses/existing', [AdminLicenseController::class, 'storeExisting'])->name('licenses.existing.store');
    Route::put('/licenses/{license}', [AdminLicenseController::class, 'update'])->name('licenses.update');
});

Route::middleware(['auth', 'tenant'])->group(function (): void {
    Route::get('/onboarding', OnboardingController::class)->name('onboarding');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
});

Route::middleware(['auth', 'tenant', 'organization.access', 'license'])->group(function (): void {
    Route::get('/notifications', [CommercialNotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread', [CommercialNotificationController::class, 'unread'])->name('notifications.unread');
    Route::get('/notifications/{notification}/open', [CommercialNotificationController::class, 'open'])->name('notifications.open');
    Route::patch('/notifications/{notification}/read', [CommercialNotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [CommercialNotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/create', [LeadController::class, 'create'])->middleware('role:owner,sales')->name('leads.create');
    Route::post('/leads', [LeadController::class, 'store'])->middleware('role:owner,sales')->name('leads.store');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::patch('/leads/{lead}', [LeadController::class, 'update'])->middleware('role:owner,sales')->name('leads.update');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->middleware('role:owner')->name('leads.destroy');
    Route::get('/inbound-emails', [InboundEmailController::class, 'index'])->middleware('role:owner,sales')->name('inbound-emails.index');
    Route::post('/inbound-emails/{email}/link', [InboundEmailController::class, 'link'])->middleware('role:owner,sales')->name('inbound-emails.link');
    Route::delete('/inbound-emails/{email}', [InboundEmailController::class, 'destroy'])->middleware('role:owner,sales')->name('inbound-emails.destroy');
    Route::post('/leads/{lead}/analyze', [LeadAnalysisController::class, 'store'])->middleware('role:owner,sales')->name('leads.analyze');
    Route::patch('/leads/{lead}/analyses/{analysis}', [LeadAnalysisController::class, 'update'])->middleware('role:owner,sales')->name('analyses.update');
    Route::patch('/leads/{lead}/replies/{reply}', [LeadReplyController::class, 'update'])->middleware('role:owner,sales')->name('replies.update');
    Route::post('/leads/{lead}/replies/{reply}/send', [LeadReplyController::class, 'send'])->middleware('role:owner,sales')->name('replies.send');
    Route::get('/settings/organization', [OrganizationSettingsController::class, 'edit'])->middleware('role:owner')->name('settings.organization');
    Route::put('/settings/organization', [OrganizationSettingsController::class, 'update'])->middleware('role:owner')->name('settings.organization.update');
    Route::get('/settings/users', [OrganizationUserController::class, 'index'])->middleware('role:owner')->name('settings.users.index');
    Route::post('/settings/users', [OrganizationUserController::class, 'store'])->middleware('role:owner')->name('settings.users.store');
    Route::delete('/settings/users/{user}', [OrganizationUserController::class, 'destroy'])->middleware('role:owner')->name('settings.users.destroy');
    Route::get('/settings/mailboxes', [MailboxAccountController::class, 'index'])->middleware('role:owner')->name('settings.mailboxes.index');
    Route::post('/settings/mailboxes', [MailboxAccountController::class, 'store'])->middleware('role:owner')->name('settings.mailboxes.store');
    Route::put('/settings/mailboxes/{mailbox}', [MailboxAccountController::class, 'update'])->middleware('role:owner')->name('settings.mailboxes.update');
    Route::post('/settings/mailboxes/{mailbox}/test', [MailboxAccountController::class, 'test'])->middleware('role:owner')->name('settings.mailboxes.test');
    Route::delete('/settings/mailboxes/{mailbox}', [MailboxAccountController::class, 'destroy'])->middleware('role:owner')->name('settings.mailboxes.destroy');
    Route::post('/settings/pricing-rules', [PricingRuleController::class, 'store'])->middleware('role:owner')->name('settings.pricing-rules.store');
    Route::put('/settings/pricing-rules/{rule}', [PricingRuleController::class, 'update'])->middleware('role:owner')->name('settings.pricing-rules.update');
    Route::get('/settings/sources', [InboundSourceController::class, 'index'])->middleware('role:owner')->name('settings.sources');
    Route::post('/settings/sources', [InboundSourceController::class, 'store'])->middleware('role:owner')->name('settings.sources.store');
    Route::put('/settings/sources/{source}', [InboundSourceController::class, 'update'])->middleware('role:owner')->name('settings.sources.update');
    Route::patch('/settings/sources/{source}/rotate-endpoint', [InboundSourceController::class, 'rotateEndpoint'])->middleware('role:owner')->name('settings.sources.rotate-endpoint');
    Route::get('/knowledge', [KnowledgeDocumentController::class, 'index'])->name('knowledge.index');
    Route::get('/knowledge/create', [KnowledgeDocumentController::class, 'create'])->middleware('role:owner')->name('knowledge.create');
    Route::post('/knowledge', [KnowledgeDocumentController::class, 'store'])->middleware('role:owner')->name('knowledge.store');
    Route::get('/knowledge/{document}/edit', [KnowledgeDocumentController::class, 'edit'])->middleware('role:owner')->name('knowledge.edit');
    Route::put('/knowledge/{document}', [KnowledgeDocumentController::class, 'update'])->middleware('role:owner')->name('knowledge.update');
});
