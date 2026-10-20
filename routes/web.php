<?php

use App\Http\Controllers\AuthManager;
use Illuminate\Support\Facades\Route;


Route::get('/admin', function () {
    return view('Admin.dashboard');
});

Route::get('/', function () {
    return view('LoginReg.home');
})->name('home');

Route::get('/login', [AuthManager::class, 'login'])->name('login');
Route::post('/login', [AuthManager::class, 'loginPost'])->name('login.post');
Route::get('/registration', [AuthManager::class, 'registration'])->name('registration');
Route::post('/registration', [AuthManager::class, 'registrationPost'])->name('registration.post');
Route::get('/logout', [AuthManager::class, 'logout'])->name('logout');
Route::get('/book', function () {
    return view('LoginReg.booking');
})->name('book');
