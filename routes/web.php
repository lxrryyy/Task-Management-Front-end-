<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['api.auth'])->name('dashboard');

Route::get('/calendar', function () {
    return view('calendar');
})->middleware(['api.auth'])->name('Calendar');

Route::get('/time-logs', function () {
    return view('time-logs');
})->middleware(['api.auth'])->name('Time Logs');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::post('/login', [LoginController::class, 'login'])->name('login');
