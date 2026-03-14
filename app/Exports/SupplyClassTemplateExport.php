<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SupplyClassTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['supply_class', 'category', 'source'];
    }

    public function array(): array
    {
        return [
            ['Class A', 'Category 1', 'Source A'],
            ['Class B', 'Category 2', 'Source B'],
        ];
    }
}
