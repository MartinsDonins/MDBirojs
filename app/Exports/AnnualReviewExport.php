<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel export of the annual review — one row per (non-ignored) transaction with its
 * classification and a transfer counter-leg column, so the data can be filtered and
 * sorted in a spreadsheet. Built from {@see \App\Services\AnnualReviewService::build()}.
 */
class AnnualReviewExport implements FromArray, WithHeadings, WithColumnWidths, WithStyles, WithTitle
{
    /** @param array<string,mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function title(): string
    {
        return ($this->data['year'] ?? '') . '. gads';
    }

    public function array(): array
    {
        $out = [];
        foreach ($this->data['rows'] as $row) {
            $counter = '';
            if ($row['type'] === 'TRANSFER') {
                $counter = $row['counter_status']
                    ? '✔ ' . ($row['counter_account'] ?? '')
                    : '✗ NAV pretdarījuma';
            }

            $out[] = [
                $row['n'],
                $row['date'],
                $row['account'],
                $row['partner'],
                $row['description'],
                $row['category'],
                $row['label'],
                number_format((float) $row['amount'], 2, ',', ' '),
                $row['currency'],
                $row['currency'] === 'EUR' ? '' : number_format((float) $row['amount_original'], 2, ',', ' '),
                $this->statusLabel($row['status']),
                $counter,
            ];
        }

        return $out;
    }

    public function headings(): array
    {
        return [
            'Nr.', 'Datums', 'Konts', 'Partneris', 'Apraksts', 'Kategorija',
            'Veids', 'Summa EUR', 'Valūta', 'Oriģ. summa', 'Statuss', 'Pretdarījums',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   'B' => 12,  'C' => 16,  'D' => 22,  'E' => 40,  'F' => 22,
            'G' => 24,  'H' => 13,  'I' => 8,   'J' => 12,  'K' => 14,  'L' => 22,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF374151']],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFDBEAFE'], // blue-100
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->freezePane('A2');

        return [];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'COMPLETED'    => 'Apstiprināts',
            'DRAFT'        => 'Melnraksts',
            'NEEDS_REVIEW' => 'Pārbaudāms',
            'IGNORED'      => 'Ignorēts',
            default        => $status,
        };
    }
}
