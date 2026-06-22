<?php

use App\Exports\CashImportTemplate;
use App\Http\Controllers\AnnualReportController;
use App\Http\Controllers\GidDocumentController;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Download the Excel import template for cash/PayPal/Paysera transactions.
 * Protected by auth so only logged-in users can access it.
 */
Route::get('/admin/excel-template/cash', function () {
    return Excel::download(new CashImportTemplate, 'kases-imports-paraugs.xlsx');
})->middleware(['auth'])->name('excel.template.cash');

/**
 * Annual review report (PDF / Excel) — a chronological listing of every transaction
 * of a year, classified for review. Protected by auth.
 */
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/reports/annual/{year}/pdf', [AnnualReportController::class, 'pdf'])
        ->whereNumber('year')
        ->name('reports.annual.pdf');

    Route::get('/admin/reports/annual/{year}/excel', [AnnualReportController::class, 'excel'])
        ->whereNumber('year')
        ->name('reports.annual.excel');

    // Pre-filled VID D3 annex (saimnieciskās darbības ienākumi) as PDF.
    Route::get('/admin/reports/d3/{year}/pdf', [AnnualReportController::class, 'd3Pdf'])
        ->whereNumber('year')
        ->name('reports.d3.pdf');

    // Serve a stored EDS document (GID XML/HTML/PDF, IIN XML).
    Route::get('/admin/gid/document/{document}', [GidDocumentController::class, 'show'])
        ->name('gid.document');
});
