<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class FacilityMonthlyConsumptionTemplateExport implements FromArray, WithHeadings, WithColumnWidths, WithStyles
{
    public function __construct(
        protected array $items,
        protected array $monthYears
    ) {}

    public function array(): array
    {
        $rows = [];
        foreach ($this->items as $name) {
            $row = [$name];
            foreach ($this->monthYears as $monthYear) {
                $row[] = '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        $headers = ['Item'];
        foreach ($this->monthYears as $monthYear) {
            $dt = \DateTime::createFromFormat('Y-m', $monthYear);
            $headers[] = $dt ? $dt->format('M-y') : $monthYear; // e.g. "Jan-26"
        }
        return $headers;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 45];
        $colIndex = 2; // B
        foreach ($this->monthYears as $_) {
            $widths[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex)] = 14;
            $colIndex++;
        }
        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->monthYears) + 1);
        $lastRow = count($this->items) + 1;

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'], // blue-600
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E7EB'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        return [];
    }
}

