<?php

use App\Http\Controllers\AdminAnnouncementController;
use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminReviewController;
use App\Http\Controllers\AdminRoomsController;
use App\Http\Controllers\AdminSettingController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthManager;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CustomerSettingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WalkInController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\CheckInStaffOnly;
use App\Http\Middleware\VerifyPayMongoSignature;
use App\Models\Review;
use Illuminate\Support\Facades\Route;

// Public pages

Route::get('/', function () {
    $reviews = Review::latest()->take(3)->get();
    $reviewCount = Review::count();
    $reviewAverage = Review::avg('rating') ?? 0;

    return view('LoginReg.home', compact('reviews', 'reviewCount', 'reviewAverage'));
})->name('home');

Route::get('/book', [ReservationController::class, 'create'])->name('book');

Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');

// Authentication
Route::get('/login', [AuthManager::class, 'login'])->name('login');
Route::post('/login', [AuthManager::class, 'loginPost'])->name('login.post');
Route::get('/registration', [AuthManager::class, 'registration'])->name('registration');
Route::post('/registration', [AuthManager::class, 'registrationPost'])->name('registration.post');
Route::get('/logout', [AuthManager::class, 'logout'])->name('logout');

// Authenticated customer features
Route::middleware('auth')->group(function () {
    // Account settings
    Route::get('/account/settings', [CustomerSettingController::class, 'index'])->name('account.settings');
    Route::put('/account/settings', [CustomerSettingController::class, 'update'])->name('account.settings.update');

    // Reservations (static paths must precede the booking parameter)
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/disabled-dates', [ReservationController::class, 'disabledDates'])->name('reservations.disabled-dates');
    Route::get('/reservations/availability', [ReservationController::class, 'checkAvailability'])->name('reservations.availability');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('/reservations/{booking}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::get('/reservations/{booking}/qr-code', [CheckInController::class, 'qr'])->name('reservations.qr');

    // Payments
    Route::post('/reservations/{booking}/pay', [PaymentController::class, 'store'])->name('payment.store');
    Route::get('/reservations/{booking}/payment-success', [PaymentController::class, 'success'])->name('payment.success');

    // Reviews and announcements
    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::post('/announcements/read', [AnnouncementController::class, 'markAllRead'])->name('announcements.read');
});

Route::middleware(['auth', CheckInStaffOnly::class])->prefix('check-in')->name('check-in.')->group(function () {
    Route::get('/', [CheckInController::class, 'index'])->name('index');
    Route::post('/preview', [CheckInController::class, 'preview'])->middleware('throttle:30,1')->name('preview');
    Route::post('/', [CheckInController::class, 'store'])->middleware('throttle:30,1')->name('store');
});

Route::middleware(['auth', CheckInStaffOnly::class])->prefix('walk-ins')->name('walk-ins.')->group(function () {
    Route::get('/create', [ReservationController::class, 'create'])->name('create');
    Route::post('/', [ReservationController::class, 'store'])->name('store');
    Route::get('/{booking}', [WalkInController::class, 'show'])->name('show');
    Route::post('/{booking}/payment', [WalkInController::class, 'pay'])->name('payment');
    Route::post('/{booking}/check-in', [WalkInController::class, 'checkIn'])->name('check-in');
    Route::get('/{booking}/qr-code', [CheckInController::class, 'qr'])->name('qr');
});

// Admin management
Route::middleware(['auth', AdminOnly::class])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    // Rooms and cottages
    Route::get('/rooms', [AdminRoomsController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/create', [AdminRoomsController::class, 'create'])->name('rooms.create');
    Route::post('/rooms', [AdminRoomsController::class, 'store'])->name('rooms.store');
    Route::get('/rooms/{cottage}/edit', [AdminRoomsController::class, 'edit'])->name('rooms.edit');
    Route::put('/rooms/{cottage}', [AdminRoomsController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{cottage}', [AdminRoomsController::class, 'destroy'])->name('rooms.destroy');

    // Bookings
    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}', [AdminBookingController::class, 'update'])->name('bookings.update');
    Route::post('/bookings/{booking}/balance', [AdminBookingController::class, 'settleBalance'])->name('bookings.balance');
    Route::get('/book', [ReservationController::class, 'create'])->name('book');

    // Reports
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [AdminReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/print', [AdminReportController::class, 'printable'])->name('reports.print');

    // Reviews
    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/respond', [AdminReviewController::class, 'respond'])->name('reviews.respond');
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

    // Settings
    Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');

    // Announcements
    Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');
});

// Payment webhook
Route::post('/webhooks/paymongo', [PaymentController::class, 'webhook'])
    ->middleware(VerifyPayMongoSignature::class)
    ->name('webhooks.paymongo');

// Legacy room preview
Route::get('/admin/include/rooms', function () {
    return redirect()->route('admin.rooms.index');
})->middleware(['auth', AdminOnly::class]);
