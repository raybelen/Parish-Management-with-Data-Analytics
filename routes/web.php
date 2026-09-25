<?php

use App\Http\Controllers\ParishPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', ParishPageController::class)->name('home');
Route::get('/about', ParishPageController::class)->defaults('page', 'about')->name('about');
Route::get('/services', ParishPageController::class)->defaults('page', 'services')->name('services');
Route::get('/announcements', ParishPageController::class)->defaults('page', 'announcements')->name('announcements');
Route::get('/ministries-and-organizations', ParishPageController::class)->defaults('page', 'ministries')->name('ministries');
Route::get('/gallery', ParishPageController::class)->defaults('page', 'gallery')->name('gallery');
Route::get('/contact', ParishPageController::class)->defaults('page', 'contact')->name('contact');
