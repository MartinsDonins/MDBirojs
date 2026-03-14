<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CashImportTemplate implements FromArray, WithHeadings, WithColumnWidths, WithStyles
{
    public function array(): array
    {
        return [
            ['15.01.2024', 'PayPal',  'Saņemts',   'Cloud Linux Ltd',  'Invoice #12345 — servera abonements', '45.50', 'EUR'],
            ['20.01.2024', 'Kase',    'Izsniegts',  'Biroja preces SIA', 'Kancelejas preču pirkums',            '23.00', 'EUR'],
            ['25.01.2024', 'PayPal',  'Izsniegts',  'OVH',               'Servera noma — janvāris 2024',        '39.98', 'EUR'],
            ['31.01.2024', 'Paysera', 'Saņemts',   '',                   'Klients — maksājums par pakalpojumu', '150.00','EUR'],
        ];
    }

    public function headings(): array
    {
        return ['Datums', 'Konts', 'Tips', 'Partneris', 'Apraksts', 'Summa', 'Valūta'];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,  // Datums
            'B' => 14,  // Konts
            'C' => 13,  // Tips
            'D' => 22,  // Partneris
            'E' => 45,  // Apraksts
            'F' => 10,  // Summa
            'G' => 8,   // Valūta
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header row: bold + light background
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF374151']],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE9D5FF'], // violet-200
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows: center date/type/currency columns
        $sheet->getStyle('A2:A100')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C2:C100')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G2:G100')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F2:F100')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Note row below examples
        $sheet->setCellValue('A6', '← Aizstāj šīs rindas ar saviem datumiem. 1. rindu (virsrakstus) neskar!');
        $sheet->getStyle('A6:G6')->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['argb' => 'FF9CA3AF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFAFAFA']],
        ]);
        $sheet->mergeCells('A6:G6');

        // Tips column instruction
        $sheet->setCellValue('A7', 'Tips: Saņemts = nauda ieņemta (KII), Izsniegts = nauda izmaksāta (KIO)');
        $sheet->getStyle('A7:G7')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['argb' => 'FF9CA3AF']],
        ]);
        $sheet->mergeCells('A7:G7');

        return [];
    }
}
