<?php

use App\Http\Controllers\AdminPasswordController;
use App\Http\Controllers\AdminRoleController;
use App\Http\Controllers\AdminSecurityController;
use App\Http\Controllers\AdminTwoFactorSessionController;
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
        Route::view('/', 'admin.dashboard')->name('dashboard');
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
