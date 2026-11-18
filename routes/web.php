<?php

use App\Http\Controllers\Spk\AhpInspectorController;
use App\Http\Controllers\Spk\SpkDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SpkDashboardController::class, 'index'])->name('spk.dashboard');
Route::get('/export-csv', [SpkDashboardController::class, 'exportCsv'])->name('spk.export.csv');
Route::get('/customers/{customer}/dossier', [SpkDashboardController::class, 'dossier'])->name('spk.customers.dossier');
Route::post('/import-csv', [SpkDashboardController::class, 'importCsv'])->name('spk.import.csv');

Route::get('/ahp-inspector', [AhpInspectorController::class, 'index'])->name('spk.ahp.inspector');
Route::post('/ahp-inspector/preview', [AhpInspectorController::class, 'preview'])->name('spk.ahp.preview');
Route::post('/ahp-inspector/apply', [AhpInspectorController::class, 'apply'])->name('spk.ahp.apply');
Route::post('/ahp-inspector/reset', [AhpInspectorController::class, 'reset'])->name('spk.ahp.reset');
