<?php

namespace App\Imports;

use App\Models\SupplyClass;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;

class SupplyClassImport implements ToModel, WithHeadingRow, SkipsEmptyRows, SkipsOnError
{
    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $errors = [];

    public function model(array $row)
    {
        try {
            $supplyClass = trim($row['supply_class'] ?? '');
            if (empty($supplyClass)) {
                $this->skippedCount++;
                return null;
            }

            $category = !empty($row['category']) ? trim($row['category']) : null;
            $source = !empty($row['source']) ? trim($row['source']) : null;

            $this->importedCount++;

            return SupplyClass::updateOrCreate(
                ['supply_class' => $supplyClass],
                [
                    'category' => $category,
                    'source' => $source,
                    'is_active' => true,
                ]
            );
        } catch (\Exception $e) {
            $this->errors[] = 'Row: ' . ($e->getMessage());
            $this->skippedCount++;
            return null;
        }
    }

    public function onError(\Throwable $e)
    {
        $this->errors[] = $e->getMessage();
        $this->skippedCount++;
    }

    public function getResults(): array
    {
        return [
            'imported' => $this->importedCount,
            'skipped' => $this->skippedCount,
            'errors' => $this->errors,
        ];
    }
}
