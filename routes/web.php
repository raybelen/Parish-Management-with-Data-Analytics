<?php

use App\Http\Controllers\AdminAppointmentController;
use App\Http\Controllers\AdminAppointmentStatusController;
use App\Http\Controllers\AdminAuditController;
use App\Http\Controllers\AdminBackupController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminDocumentController;
use App\Http\Controllers\AdminForecastController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminPasswordController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\AdminPersonnelController;
use App\Http\Controllers\AdminRecordController;
use App\Http\Controllers\AdminRecordImportController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminRoleController;
use App\Http\Controllers\AdminScheduleBlockController;
use App\Http\Controllers\AdminSecurityController;
use App\Http\Controllers\AdminServiceTypeController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminTwoFactorSessionController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminWebsiteContentController;
use App\Http\Controllers\AppointmentCancellationController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentLookupController;
use App\Http\Controllers\AppointmentReferenceRecoveryController;
use App\Http\Controllers\AppointmentScheduleController;
use App\Http\Controllers\ParishCalendarController;
use App\Http\Controllers\ParishPageController;
use App\Http\Middleware\EnsureTwoFactorChallenge;
use App\Http\Middleware\PrepareAdminLogin;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware('guest:web')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware(['throttle:admin-login', PrepareAdminLogin::class])->block()->name('login.store');
    Route::middleware(EnsureTwoFactorChallenge::class)->group(function (): void {
        Route::get('/two-factor-challenge', [AdminTwoFactorSessionController::class, 'create'])->name('two-factor.login');
        Route::post('/two-factor-challenge', [AdminTwoFactorSessionController::class, 'store'])
            ->middleware('throttle:admin-mfa')->block()->name('two-factor.login.store');
    });
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->block()->name('logout');

Route::prefix('admin')->name('admin.')->middleware(['auth:web', 'admin', 'auth.session'])->group(function (): void {
    Route::middleware('admin.mfa:enrollment')->group(function (): void {
        Route::get('/security', [AdminSecurityController::class, 'show'])->name('security');
        Route::post('/security/two-factor', [AdminSecurityController::class, 'store'])
            ->middleware('throttle:admin-sensitive')->block()->name('security.enable');
        Route::post('/security/two-factor/confirm', [AdminSecurityController::class, 'confirm'])
            ->middleware('throttle:admin-mfa')->block()->name('security.confirm');
    });
    Route::middleware('admin.mfa')->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::resource('appointments', AdminAppointmentController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::patch('appointments/{appointment}/details', [AdminAppointmentController::class, 'details'])->name('appointments.details');
        Route::post('appointments/{appointment}/status', [AdminAppointmentStatusController::class, 'store'])->name('appointments.status');
        Route::patch('appointments/{appointment}/documents', [AdminDocumentController::class, 'update'])->name('documents.update');
        Route::post('appointments/{appointment}/documents', [AdminDocumentController::class, 'store'])->name('documents.store');
        Route::get('appointments/{appointment}/documents/{type}/{index}', [AdminDocumentController::class, 'show'])->whereNumber('index')->name('documents.show');
        Route::post('appointments/{appointment}/payments', [AdminPaymentController::class, 'store'])->name('payments.store');
        Route::patch('appointments/{appointment}/fee', [AdminPaymentController::class, 'update'])->name('payments.fee');
        Route::get('records/import-template', [AdminRecordImportController::class, 'create'])->name('records.template');
        Route::post('records/import', [AdminRecordImportController::class, 'store'])->middleware('throttle:10,1')->name('records.import');
        Route::get('records/all', [AdminRecordController::class, 'all'])->name('records.all');
        Route::get('records/services/{serviceType:slug}', [AdminRecordController::class, 'service'])->name('records.service');
        Route::get('records/services/{serviceType:slug}/book', [AdminRecordController::class, 'book'])->name('records.book');
        Route::resource('records', AdminRecordController::class)->parameters(['records' => 'record'])->except('edit');
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        if (config('office.analytics_enabled')) {
            Route::get('forecasts', [AdminForecastController::class, 'index'])->name('forecasts.index');
            Route::post('forecasts', [AdminForecastController::class, 'store'])->middleware('throttle:2,1')->name('forecasts.store');
        }
        Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::get('website-content', [AdminWebsiteContentController::class, 'index'])->name('website-content.index');
        Route::put('website-content', [AdminWebsiteContentController::class, 'update'])
            ->middleware('throttle:admin-sensitive')->block()->name('website-content.update');
        Route::put('permissions', [AdminSettingsController::class, 'permissions'])->middleware('throttle:admin-sensitive')->name('permissions.update');
        Route::post('service-types', [AdminServiceTypeController::class, 'store'])->name('services.store');
        Route::put('service-types/{serviceType}', [AdminServiceTypeController::class, 'update'])->name('services.update');
        Route::post('personnel', [AdminPersonnelController::class, 'store'])->name('personnel.store');
        Route::put('personnel/{personnel}', [AdminPersonnelController::class, 'update'])->name('personnel.update');
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('users', [AdminUserController::class, 'store'])->middleware('throttle:admin-sensitive')->name('users.store');
        Route::get('audit', [AdminAuditController::class, 'index'])->name('audit.index');
        Route::post('backups', [AdminBackupController::class, 'store'])->middleware('throttle:admin-sensitive')->name('backups.store');
        Route::get('notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/{notification}', [AdminNotificationController::class, 'update'])->name('notifications.update');
        Route::post('schedule-blocks', [AdminScheduleBlockController::class, 'store'])->name('blocks.store');
        Route::delete('schedule-blocks/{block}', [AdminScheduleBlockController::class, 'destroy'])->name('blocks.destroy');
        Route::post('/security/recovery-codes', [AdminSecurityController::class, 'recoveryCodes'])
            ->middleware('throttle:admin-sensitive')->block()->name('security.recovery-codes');
        Route::put('/security/password', [AdminPasswordController::class, 'update'])
            ->middleware('throttle:admin-sensitive')->block()->name('password.update');
        Route::get('/roles', [AdminRoleController::class, 'index'])->name('roles.index');
        Route::patch('/roles/{user}', [AdminRoleController::class, 'update'])
            ->middleware('throttle:admin-sensitive')->block()->name('roles.update');
    });
});

Route::get('/', ParishPageController::class)->name('home');
Route::get('/about', ParishPageController::class)->defaults('page', 'about')->name('about');
Route::get('/services', ParishPageController::class)->defaults('page', 'services')->name('services');
Route::get('/announcements', ParishPageController::class)->defaults('page', 'announcements')->name('announcements');
Route::get('/ministries-and-organizations', ParishPageController::class)->defaults('page', 'ministries')->name('ministries');
Route::get('/gallery', ParishPageController::class)->defaults('page', 'gallery')->name('gallery');
Route::get('/contact', ParishPageController::class)->defaults('page', 'contact')->name('contact');
Route::get('/calendar', ParishCalendarController::class)->name('calendar');

Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
Route::get('/appointments/new', [AppointmentController::class, 'create'])->name('appointments.create');
Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('throttle:5,1')->name('appointments.store');
Route::get('/appointments/manage', [AppointmentLookupController::class, 'create'])->name('appointments.lookup.create');
Route::post('/appointments/manage', [AppointmentLookupController::class, 'store'])->middleware('throttle:10,1')->name('appointments.lookup.store');
Route::post('/appointments/manage/recover', [AppointmentReferenceRecoveryController::class, 'store'])->middleware('throttle:5,1')->name('appointments.reference-recovery.store');
Route::patch('/appointments/{appointment}/schedule', [AppointmentScheduleController::class, 'update'])
    ->middleware(['signed', 'throttle:5,1'])
    ->name('appointments.schedule.update');
Route::post('/appointments/{appointment}/cancellation', [AppointmentCancellationController::class, 'store'])
    ->middleware(['signed', 'throttle:5,1'])
    ->name('appointments.cancellation.store');
Route::get('/appointments/{appointment}/confirmation', [AppointmentController::class, 'show'])
    ->middleware('signed')
    ->name('appointments.show');
