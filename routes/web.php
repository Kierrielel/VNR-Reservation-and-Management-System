<?php

use App\Http\Controllers\AdminReviewController;
use App\Http\Controllers\AdminSettingController;
use App\Http\Controllers\AuthManager;
use App\Http\Controllers\ReviewController;
use App\Http\Middleware\AdminOnly;
use App\Models\Review;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAnnouncementController;
use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminRoomsController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CustomerSettingController;

//temporary
Route::get('/admin/include/rooms', function () {
    return view('Admin.include.rooms');
});

Route::get('/', function () {
    $reviews = Review::latest()->take(3)->get();
    $reviewCount = Review::count();
    $reviewAverage = Review::avg('rating') ?? 0;

    return view('LoginReg.home', compact('reviews', 'reviewCount', 'reviewAverage'));
})->name('home');

Route::get('/login', [AuthManager::class, 'login'])->name('login');
Route::post('/login', [AuthManager::class, 'loginPost'])->name('login.post');
Route::get('/registration', [AuthManager::class, 'registration'])->name('registration');
Route::post('/registration', [AuthManager::class, 'registrationPost'])->name('registration.post');
Route::get('/logout', [AuthManager::class, 'logout'])->name('logout');
Route::get('/book', function () {
    return view('LoginReg.booking');
})->name('book');

Route::middleware('auth')->group(function () {
    Route::get('/account/settings', [CustomerSettingController::class, 'index'])->name('account.settings');
    Route::put('/account/settings', [CustomerSettingController::class, 'update'])->name('account.settings.update');
});

// Admin-only pages
Route::middleware(['auth', AdminOnly::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return view('Admin.dashboard');
    })->name('dashboard');

     // for rooms/cottages
    Route::get('/rooms', [AdminRoomsController::class, 'index'])->name('rooms.index');

    // for bookings
    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    
    // for reports
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');

    // for review
    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/respond', [AdminReviewController::class, 'respond'])->name('reviews.respond');
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

    // for setting
    Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');

        // Thus is for announcemnet
    Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
    Route::patch('/announcements/{announcement}/archive', [AdminAnnouncementController::class, 'archive'])->name('announcements.archive');
    Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');

});
// REVIEW AUTH
Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
// CUSTOMER: submit a review
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');

// For user to get notifeid
Route::post('/announcements/read', [AnnouncementController::class, 'markAllRead'])
    ->middleware('auth')->name('announcements.read');
