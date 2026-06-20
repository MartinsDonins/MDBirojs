<?php

namespace App\Http\Controllers;

use App\Exports\AnnualReviewExport;
use App\Services\AnnualReviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Annual review report — a flat, chronological listing of every transaction of a
 * year, classified (income / deductible expense / non-deductible expense / transfer)
 * with a counter-transaction reconciliation, exportable as PDF or Excel.
 *
 * Lets the user scan a whole year and verify each transaction is defined correctly
 * and that cash/account transfers have their matching opposite leg.
 */
class AnnualReportController extends Controller
{
    public function __construct(private readonly AnnualReviewService $service)
    {
    }

    public function pdf(int $year)
    {
        $data = $this->service->build($year);

        $pdf = Pdf::loadView('reports.annual-review', $data)
            ->setPaper('a4', 'landscape');

        return $pdf->stream("gada-parskats-{$year}.pdf");
    }

    public function excel(int $year)
    {
        return Excel::download(
            new AnnualReviewExport($this->service->build($year)),
            "gada-parskats-{$year}.xlsx",
        );
    }
}
