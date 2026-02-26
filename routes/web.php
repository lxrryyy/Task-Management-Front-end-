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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/test-api', function () {
    try {
        $response = app(\App\Services\CsharpApiService::class)->get('/api/health');
        return response()->json([
            'status'   => 'connected',
            'response' => $response,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ]);
    }
});

require __DIR__.'/auth.php';

Route::post('/login', [LoginController::class, 'login'])->name('login');
