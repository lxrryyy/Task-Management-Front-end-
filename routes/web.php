<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\StickyNoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('/auth/login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['api.auth'])->name('dashboard');

Route::get('/calendar', function () {
    return view('calendar');
})->middleware(['api.auth'])->name('calendar');

Route::get('/projects', [ProjectController::class, 'index'])
    ->middleware(['api.auth'])
    ->name('Projects');


Route::get('/audit-logs', function () {
    return view('audit-logs');
})->middleware(['api.auth'])->name('Time Logs');

Route::get('/tasks', function () {
    return view('tasks');
})->middleware(['api.auth'])->name('Tasks');

Route::get('/projects/{project}/tasks', [TaskController::class, 'index'])
    ->middleware(['api.auth'])
    ->name('projects.tasks');

Route::get('/tasks/calculate-due-date', [TaskController::class, 'calculateDueDate'])
    ->middleware(['api.auth'])
    ->name('tasks.calculateDueDate');

Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])
    ->middleware(['api.auth'])
    ->name('tasks.store');

Route::patch('/projects/{project}/tasks/{task}/status', [TaskController::class, 'updateStatus'])
    ->middleware(['api.auth'])
    ->name('tasks.updateStatus');


Route::middleware(['api.auth'])->group(function () {
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    // Archive view (must be before /projects/{id} to avoid route collision)
    Route::get('/projects/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::get('/projects/{id}', [ProjectController::class, 'show'])->name('projects.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ── Sticky Notes (proxied to C# backend) ─────────────────────────────────────
Route::middleware(['api.auth'])->group(function () {
    Route::get   ('/notes',        [StickyNoteController::class, 'index'])  ->name('notes.index');
    Route::post  ('/notes',        [StickyNoteController::class, 'store'])  ->name('notes.store');
    Route::patch ('/notes/{id}',   [StickyNoteController::class, 'update']) ->name('notes.update');
    Route::delete('/notes/{id}',   [StickyNoteController::class, 'destroy'])->name('notes.destroy');
    // Standalone always-on-top popup window
    Route::get   ('/note-popup',   fn () => view('note-popup'))             ->name('note-popup');
});

// Logout: no auth middleware so API-only users (session api_token) can hit it and get token_forgotten redirect
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

require __DIR__.'/auth.php';

Route::post('/login', [LoginController::class, 'login'])->name('login');
