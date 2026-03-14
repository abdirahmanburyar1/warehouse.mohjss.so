<?php

namespace App\Imports;

use App\Models\MonthlyConsumptionReport;
use App\Models\MonthlyConsumptionItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Illuminate\Support\Facades\Storage;

class ProcessMonthlyConsumptionImport implements
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    SkipsEmptyRows,
    WithEvents
{
    /** @var array<string,string> heading_key => YYYY-MM */
    protected array $monthColumns = [];

    protected int $facilityId;
    protected string $importId;
    protected ?string $storedFilePath;
    protected ?string $facilityType = null;

    public int $processedRows = 0;
    public int $importedCount = 0;
    public int $updatedCount = 0;
    public array $errors = [];

    // Map month_year to report ID cache to save queries
    protected array $reportCache = [];

    public function __construct(string $importId, int $facilityId, ?string $storedFilePath = null)
    {
        $this->importId = $importId;
        $this->facilityId = $facilityId;
        $this->storedFilePath = $storedFilePath;
        
        $facility = \App\Models\Facility::find($facilityId);
        $this->facilityType = $facility?->facility_type;
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                if ($this->storedFilePath) {
                    Storage::disk('local')->delete($this->storedFilePath);
                    Log::info("Deleted imported facility file: {$this->storedFilePath}");
                }
            },
        ];
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        if (empty($this->monthColumns)) {
            $this->detectMonthColumns($rows->first());
        }

        foreach ($rows as $row) {
            $this->processRow($row);
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    protected function detectMonthColumns($row): void
    {
        $array = is_array($row) ? $row : $row->toArray();
        foreach ($array as $key => $value) {
            if ($key === null || $key === '') {
                continue;
            }
            $normalizedKey = is_string($key) ? trim(strtolower($key)) : (string) $key;
            if (in_array($normalizedKey, ['item description', 'item_description', 'item', 'items', 'product', 'amc', 'category', 'dosage form', 'dosage_form', 'product name'], true)) {
                continue;
            }
            $monthYear = $this->parseMonthKey($normalizedKey);
            if ($monthYear !== null) {
                $this->monthColumns[$key] = $monthYear;
            }
        }
    }

    protected function parseMonthKey(string $key): ?string
    {
        $clean = str_replace(['_', '-', '/'], ' ', $key);
        $clean = preg_replace('/\s+/', ' ', $clean ?? '') ?? '';
        $clean = trim($clean);
        if ($clean === '') {
            return null;
        }

        $monthMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        $normalizeYear = static function (int $year): int {
            if ($year < 100) {
                return 2000 + $year;
            }
            return $year;
        };

        if (preg_match('/^([a-z]+)\s+(\d{2}|\d{4})$/i', $clean, $m)) {
            $monthName = strtolower(substr($m[1], 0, 3));
            if (!isset($monthMap[$monthName])) {
                return null;
            }
            $year = $normalizeYear((int) $m[2]);
            return sprintf('%04d-%02d', $year, $monthMap[$monthName]);
        }

        if (preg_match('/^(\d{4})\s+(\d{1,2})$/', $clean, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            if ($month < 1 || $month > 12) {
                return null;
            }
            return sprintf('%04d-%02d', $year, $month);
        }

        return null;
    }

    protected function getReportIdForMonth(string $monthYear): int
    {
        if (!isset($this->reportCache[$monthYear])) {
            $report = MonthlyConsumptionReport::updateOrCreate(
                [
                    'facility_id' => $this->facilityId,
                    'month_year' => $monthYear
                ],
                ['generated_by' => auth()->id()]
            );
            $this->reportCache[$monthYear] = $report->id;
        }

        return $this->reportCache[$monthYear];
    }

    protected function processRow($row): void
    {
        $this->processedRows++;
        $data = is_array($row) ? $row : $row->toArray();

        $itemName = trim((string) ($data['item description'] ?? $data['item_description'] ?? $data['product name'] ?? $data['item'] ?? $data['items'] ?? $data['product'] ?? ''));
        if ($itemName === '') {
            return;
        }

        $product = Product::where('name', $itemName)->first();
        if (!$product) {
            if (count($this->errors) < 20) {
                $this->errors[] = "Product not found: {$itemName}";
            }
            return;
        }

        // Check eligibility if facility type is known
        if ($this->facilityType) {
            $isEligible = \App\Models\EligibleItem::where('product_id', $product->id)
                ->where('facility_type', $this->facilityType)
                ->exists();
            
            if (!$isEligible) {
                if (count($this->errors) < 20) {
                    $this->errors[] = "Product '{$itemName}' is not eligible for this facility type ({$this->facilityType}).";
                }
                return;
            }
        }

        foreach ($this->monthColumns as $columnKey => $monthYear) {
            if (!array_key_exists($columnKey, $data)) {
                $quantity = 0;
            } else {
                $quantity = $this->cleanQuantity($data[$columnKey]);
            }

            $reportId = $this->getReportIdForMonth($monthYear);

            $record = MonthlyConsumptionItem::updateOrCreate(
                [
                    'parent_id' => $reportId,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => (int) round($quantity),
                ]
            );

            if ($record->wasRecentlyCreated) {
                $this->importedCount++;
            } else {
                $this->updatedCount++;
            }
        }
    }

    protected function cleanQuantity($value): float
    {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            return 0.0;
        }
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
        if ($cleaned === '' || $cleaned === '-') {
            return 0.0;
        }
        $q = (float) $cleaned;
        return max(0.0, $q);
    }

    public function getResults(): array
    {
        return [
            'imported' => $this->importedCount,
            'updated' => $this->updatedCount,
            'skipped' => 0,
            'errors' => $this->errors,
        ];
    }
}