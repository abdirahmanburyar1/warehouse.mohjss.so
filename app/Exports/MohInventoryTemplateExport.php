<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MohInventoryTemplateExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Product::with('category:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                return [
                    'Item' => $product->name,
                    'Category' => $product->category?->name ?? '',
                    'UoM' => '',
                    'Source' => '',
                    'Quantity' => '',
                    'Batch No' => '',
                    'Expiry Date' => '',
                    'Location' => '',
                    'Unit Cost' => '',
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Item',
            'Category',
            'UoM',
            'Source',
            'Quantity',
            'Batch No',
            'Expiry Date',
            'Location',
            'Unit Cost',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45,  // Item
            'B' => 20,  // Category
            'C' => 10,  // UoM
            'D' => 15,  // Source
            'E' => 12,  // Quantity
            'F' => 15,  // Batch No
            'G' => 15,  // Expiry Date
            'H' => 15,  // Location
            'I' => 12,  // Unit Cost
        ];
    }
}
