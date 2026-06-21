<?php

namespace App\Http\Controllers;

use App\Exports\AnnualReviewExport;
use App\Services\AnnualReviewService;
use App\Services\D3DeclarationService;
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

    /**
     * Pre-filled VID D3 annex ("Ienākumi no saimnieciskās darbības") as PDF.
     * Journal-derived rows (4, 5, 6) are auto-filled; the rest come from the
     * per-year manual inputs saved on the D3 declaration page.
     */
    public function d3Pdf(int $year, D3DeclarationService $d3)
    {
        $pdf = Pdf::loadView('reports.d3-declaration', $d3->fullReport($year))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("d3-deklaracija-{$year}.pdf");
    }
}
