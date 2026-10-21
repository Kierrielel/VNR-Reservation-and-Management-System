<?php

use App\Http\Controllers\AdminReviewController;
use App\Http\Controllers\AuthManager;
use App\Http\Controllers\ReviewController;
use App\Models\Review;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminSettingController;


Route::get('/admin', function () {
    return view('Admin.dashboard');
});

Route::get('/', function () {
    $reviews       = Review::latest()->take(3)->get();   
    $reviewCount   = Review::count();                    
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

// FOR SETTINF admin
Route::middleware(['auth', \App\Http\Middleware\AdminOnly::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/respond', [AdminReviewController::class, 'respond'])->name('reviews.respond');
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
});
// REVIEW AUTH

// CUSTOMER: submit a review
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store')  ;

// ADMIN ONLY: see, answer and delete reviews
Route::middleware(['auth', \App\Http\Middleware\AdminOnly::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/respond', [AdminReviewController::class, 'respond'])->name('reviews.respond');
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');
});