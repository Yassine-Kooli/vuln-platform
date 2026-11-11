<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TargetController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/targets/create', [TargetController::class, 'create'])->name('targets.create');
Route::post('/targets', [TargetController::class, 'store'])->name('targets.store');
Route::get('/targets/{target}', [TargetController::class, 'show'])->name('targets.show');
Route::post('/targets/{target}/snapshot', [TargetController::class, 'snapshot'])->name('targets.snapshot');
Route::delete('/targets/{target}', [TargetController::class, 'destroy'])->name('targets.destroy');

Route::post('/targets/{target}/scans', [ScanController::class, 'store'])->name('scans.store');
Route::post('/scans/all', [ScanController::class, 'storeAll'])->name('scans.all');
Route::get('/scans/{scan}', [ScanController::class, 'show'])->name('scans.show');
Route::get('/scans/{scan}/status', [ScanController::class, 'status'])->name('scans.status');
Route::get('/scans/{scan}/files/{file}', [ScanController::class, 'file'])->name('scans.file');
