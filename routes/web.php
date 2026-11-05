<?php

use App\Http\Controllers\DemoWebhookController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Middleware\DemoOnly;
use App\Http\Middleware\LimitExtractions;
use Illuminate\Support\Facades\Route;

Route::get('/', [InvoiceController::class, 'home'])->name('home');

Route::controller(InvoiceController::class)->group(function () {
    Route::get('/invoices', 'index')->name('invoices.index');
    Route::get('/invoices/upload', 'create')->name('invoices.create');
    Route::post('/invoices', 'store')->middleware(LimitExtractions::class)->name('invoices.store');
    Route::get('/invoices/{invoice}', 'show')->name('invoices.show');
    Route::put('/invoices/{invoice}', 'update')->name('invoices.update');
    Route::post('/invoices/{invoice}/approve', 'approve')->name('invoices.approve');
    Route::post('/invoices/{invoice}/retry', 'retry')->middleware(LimitExtractions::class)->name('invoices.retry');
    Route::delete('/invoices/{invoice}', 'destroy')->name('invoices.destroy');
    Route::get('/invoices/{invoice}/file', 'file')->name('invoices.file');
});

Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');
Route::get('/exports/{file}', [ExportController::class, 'show'])->name('exports.show');

Route::middleware(DemoOnly::class)->group(function () {
    Route::post('/demo/samples', [InvoiceController::class, 'storeSample'])->middleware(LimitExtractions::class)->name('demo.samples');
    Route::get('/demo/webhook', [DemoWebhookController::class, 'index'])->name('demo.webhook');
    Route::post('/demo/webhook', [DemoWebhookController::class, 'store']);
});
