<?php

namespace App\Exports;

use App\Models\EligibleItem;
use App\Models\Facility;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FacilityInventoryTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected int $facilityId;

    public function __construct(int $facilityId)
    {
        $this->facilityId = $facilityId;
    }

    public function headings(): array
    {
        return ['Item', 'Category', 'Quantity', 'Batch No', 'Expiry Date', 'UoM', 'Barcode', 'Unit Cost'];
    }

    public function array(): array
    {
        $facility = Facility::find($this->facilityId);
        if (!$facility || !$facility->facility_type) {
            return [];
        }

        $eligibleItems = EligibleItem::with(['product' => fn($q) => $q->with('category:id,name')->where('is_active', true)])
            ->where('facility_type', $facility->facility_type)
            ->whereHas('product', fn($q) => $q->where('is_active', true))
            ->get()
            ->sortBy(fn($e) => $e->product?->name ?? '')
            ->values();

        $data = [];
        foreach ($eligibleItems as $eligible) {
            $product = $eligible->product;
            if (!$product) {
                continue;
            }
            $data[] = [
                $product->name,
                $product->category?->name ?? '',
                '',  // Quantity - user fills
                '',  // Batch No - user fills
                '',  // Expiry Date - user fills (e.g. 2025-12-31 or Dec-25)
                '',  // UoM - optional
                '',  // Barcode - optional
                '',  // Unit Cost - optional
            ];
        }

        return $data;
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
            'A' => 40,  // Item
            'B' => 20,  // Category
            'C' => 12,  // Quantity
            'D' => 18,  // Batch No
            'E' => 15,  // Expiry Date
            'F' => 10,  // UoM
            'G' => 18,  // Barcode
            'H' => 12,  // Unit Cost
        ];
    }
}
