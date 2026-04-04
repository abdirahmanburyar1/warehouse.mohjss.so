<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class UnifiedLmisReportExport implements FromArray, WithHeadings, WithStyles, WithEvents, WithColumnWidths
{
    protected $rows;
    protected $meta;

    public function __construct(array $rows, array $meta = [])
    {
        $this->rows = $rows;
        $this->meta = $meta;
    }

    public function array(): array
    {
        $data = [];
        foreach ($this->rows as $row) {
            $data[] = [
                $row['item'],
                $row['category'],
                $row['uom'],
                $row['batch_no'] ?: '–',
                $row['expiry_date'] ?: '–',
                $row['opening_balance'],
                $row['stock_received'],
                $row['stock_issued'],
                $row['negative_adjustments'], // (-)
                $row['positive_adjustments'], // (+)
                $row['closing_balance'],
                $row['total_closing_balance'],
                $row['amc'],
                $row['mos'],
                $row['stockout_days'],
            ];
        }
        return $data;
    }

    public function headings(): array
    {
        return [
            ['FACILITY LMIS REPORT'],
            ['Facility:', $this->meta['facility_name'] ?? '–', 'Period:', $this->meta['report_period'] ?? '–', 'Status:', strtoupper($this->meta['report_status'] ?? '–')],
            [], // Spacer
            [
                'ITEM',
                'CATEGORY',
                'UOM',
                'ITEM DETAILS (BATCH LEVEL)',
                '', // Span for Expiry
                'BEGINNING BALANCE',
                'QTY RECEIVED',
                'QTY ISSUED',
                'ADJUSTMENTS',
                '', // Span for (+)
                'CLOSING BALANCE',
                'TOTAL CL. BAL.',
                'AMC',
                'MOS',
                'STOCKOUT DAYS'
            ],
            [
                '', // Item
                '', // Category
                '', // UoM
                'BATCH NO:',
                'EXPIRY',
                '', // Beg Bal
                '', // Qty Rec
                '', // Qty Iss
                '(-)',
                '(+)',
                '', // Closing
                '', // Total Cl
                '', // AMC
                '', // MOS
                ''  // Stockout
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 35, // Item
            'B' => 15, // Category
            'C' => 12, // UoM
            'D' => 18, // Batch
            'E' => 15, // Expiry
            'F' => 15, // Beg Bal
            'G' => 12, // Rec
            'H' => 12, // Iss
            'I' => 10, // (-)
            'J' => 10, // (+)
            'K' => 15, // Closing
            'L' => 15, // Total Cl
            'M' => 10, // AMC
            'N' => 10, // MOS
            'O' => 12, // Stockout
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']]],
            5 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Merging Headers
                $sheet->mergeCells('A1:O1');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Meta row merging
                $sheet->mergeCells('B2:C2');
                $sheet->mergeCells('D2:E2');

                // Complex Header Merging (Row 4 & 5)
                $sheet->mergeCells('A4:A5'); // Item
                $sheet->mergeCells('B4:B5'); // Category
                $sheet->mergeCells('C4:C5'); // UoM
                $sheet->mergeCells('D4:E4'); // Item Details header
                $sheet->mergeCells('F4:F5'); // Beg Bal
                $sheet->mergeCells('G4:G5'); // Qty Rec
                $sheet->mergeCells('H4:H5'); // Qty Iss
                $sheet->mergeCells('I4:J4'); // Adjustments header
                $sheet->mergeCells('K4:K5'); // Closing
                $sheet->mergeCells('L4:L5'); // Total Cl
                $sheet->mergeCells('M4:M5'); // AMC
                $sheet->mergeCells('N4:N5'); // MOS
                $sheet->mergeCells('O4:O5'); // Stockout

                $sheet->getStyle('A4:O5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4:O5')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                // Data Merging Logic
                $startRow = 6;
                $currentRow = $startRow;
                
                foreach ($this->rows as $index => $row) {
                    if ($row['is_first_batch'] && $row['rowspan'] > 1) {
                        $endRow = $currentRow + $row['rowspan'] - 1;
                        
                        // Merge columns that should span batches
                        $sheet->mergeCells("A{$currentRow}:A{$endRow}"); // Item
                        $sheet->mergeCells("B{$currentRow}:B{$endRow}"); // Category
                        $sheet->mergeCells("C{$currentRow}:C{$endRow}"); // UoM
                        $sheet->mergeCells("L{$currentRow}:L{$endRow}"); // Total Closing
                        $sheet->mergeCells("M{$currentRow}:M{$endRow}"); // AMC
                        $sheet->mergeCells("N{$currentRow}:N{$endRow}"); // MOS
                        $sheet->mergeCells("O{$currentRow}:O{$endRow}"); // Stockout
                    }
                    $currentRow++;
                }

                // Global styles
                $sheet->getStyle("A4:O" . ($currentRow - 1))->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);

                // Alignment for numeric columns
                $sheet->getStyle("F6:O" . ($currentRow - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
