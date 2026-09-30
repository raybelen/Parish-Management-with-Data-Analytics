<?php

use App\Http\Controllers\AppointmentCancellationController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentLookupController;
use App\Http\Controllers\AppointmentReferenceRecoveryController;
use App\Http\Controllers\AppointmentScheduleController;
use App\Http\Controllers\ParishCalendarController;
use App\Http\Controllers\ParishPageController;
use Illuminate\Support\Facades\Route;

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
