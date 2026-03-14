<?php

use App\Exports\CashImportTemplate;
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
