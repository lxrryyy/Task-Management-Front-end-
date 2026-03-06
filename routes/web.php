<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('/auth/login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['api.auth'])->name('dashboard');

Route::get('/calendar', function () {
    return view('calendar');
})->middleware(['api.auth'])->name('Calendar');

Route::get('/projects', [ProjectController::class, 'index'])
    ->middleware(['api.auth'])
    ->name('Projects');


Route::get('/time-logs', function () {
    return view('time-logs');
})->middleware(['api.auth'])->name('Time Logs');

Route::get('/tasks', function () {
    return view('tasks');
})->middleware(['api.auth'])->name('Tasks');

Route::get('/projects/{project}/tasks', [TaskController::class, 'index'])
    ->middleware(['api.auth'])
    ->name('projects.tasks');

Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])
    ->middleware(['api.auth'])
    ->name('tasks.store');

Route::patch('/projects/{project}/tasks/{task}/status', [TaskController::class, 'updateStatus'])
    ->middleware(['api.auth'])
    ->name('tasks.updateStatus');

Route::middleware(['api.auth'])->group(function () {
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::get('/projects/{id}', [ProjectController::class, 'show'])->name('projects.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Logout: no auth middleware so API-only users (session api_token) can hit it and get token_forgotten redirect
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

require __DIR__.'/auth.php';

Route::post('/login', [LoginController::class, 'login'])->name('login');
