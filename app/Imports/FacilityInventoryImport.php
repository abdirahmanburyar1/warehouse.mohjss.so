<?php

namespace App\Imports;

use App\Models\EligibleItem;
use App\Models\Facility;
use App\Models\FacilityInventory;
use App\Models\FacilityInventoryItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FacilityInventoryImport implements ToCollection, WithHeadingRow
{
    private int $facilityId;
    private ?string $facilityType = null;

    public int $createdCount = 0;

    public function __construct(int $facilityId)
    {
        $this->facilityId = $facilityId;
        $facility = Facility::find($facilityId);
        $this->facilityType = $facility?->facility_type;
    }

    public function collection(Collection $rows): void
    {
        if (!$this->facilityType) {
            throw new \Exception('Facility not found or has no facility type.');
        }

        $eligibleProductIds = EligibleItem::where('facility_type', $this->facilityType)
            ->pluck('product_id')
            ->flip()
            ->all();

        $parsedRows = [];
        foreach ($rows as $index => $row) {
            $itemName = $row['item'] ?? $row['Item'] ?? null;
            $quantityRaw = $row['quantity'] ?? $row['Quantity'] ?? null;
            $batchNumber = $row['batch_no'] ?? $row['Batch No'] ?? $row['BATCH_NO'] ?? $row['batch_number'] ?? null;
            $expiryDateValue = $row['expiry_date'] ?? $row['Expiry Date'] ?? $row['EXPIRY_DATE'] ?? $row['expiry'] ?? null;

            if (empty($itemName) || $quantityRaw === null || $quantityRaw === '' || empty($batchNumber) || empty($expiryDateValue)) {
                continue;
            }

            $cleanName = trim(preg_replace('/\s+/', ' ', (string) $itemName));
            if ($cleanName === '') {
                continue;
            }

            $product = Product::where('name', $cleanName)->first()
                ?? Product::where('name', 'LIKE', '%' . $cleanName . '%')->first();

            if (!$product) {
                throw new \Exception('There are uneligible items for the facility.', 422);
            }

            if (!isset($eligibleProductIds[$product->id])) {
                throw new \Exception('There are uneligible items for the facility.', 422);
            }

            $expiryDate = $this->parseExpiryDate($expiryDateValue);
            if (!$expiryDate) {
                throw new \Exception('There are uneligible items for the facility.', 422);
            }

            $rowQuantity = (float) $quantityRaw;
            if ($rowQuantity < 0) {
                continue;
            }

            $parsedRows[] = [
                'product' => $product,
                'batch_number' => trim((string) $batchNumber),
                'expiry_date' => $expiryDate,
                'quantity' => (int) round($rowQuantity),
                'uom' => trim((string) ($row['uom'] ?? $row['UoM'] ?? $row['UOM'] ?? '')) ?: null,
                'barcode' => trim((string) ($row['barcode'] ?? $row['Barcode'] ?? $row['BARCODE'] ?? '')) ?: null,
                'unit_cost' => $this->parseUnitCost($row),
                'total_cost' => 0,
            ];
        }

        if (empty($parsedRows)) {
            throw new \Exception('No valid inventory items found in the file.', 422);
        }

        DB::transaction(function () use ($parsedRows) {
            foreach ($parsedRows as $data) {
                $inventory = $this->getFacilityInventory($data['product']->id);
                $totalCost = ($data['unit_cost'] ?? 0) * $data['quantity'];
                $data['total_cost'] = $totalCost;

                $existingItem = FacilityInventoryItem::where('facility_inventory_id', $inventory->id)
                    ->where('product_id', $data['product']->id)
                    ->where('batch_number', $data['batch_number'])
                    ->whereDate('expiry_date', $data['expiry_date'])
                    ->first();

                if ($existingItem) {
                    $existingItem->increment('quantity', $data['quantity']);
                    if (!empty($data['uom']) && empty($existingItem->uom)) {
                        $existingItem->uom = $data['uom'];
                    }
                    if (!empty($data['barcode']) && empty($existingItem->barcode)) {
                        $existingItem->barcode = $data['barcode'];
                    }
                    if (!empty($data['unit_cost']) && ((float) ($existingItem->unit_cost ?? 0) <= 0)) {
                        $existingItem->unit_cost = $data['unit_cost'];
                    }
                    $existingItem->total_cost = ((float) ($existingItem->total_cost ?? 0)) + $totalCost;
                    $existingItem->save();
                } else {
                    FacilityInventoryItem::create([
                        'facility_inventory_id' => $inventory->id,
                        'product_id' => $data['product']->id,
                        'quantity' => $data['quantity'],
                        'expiry_date' => $data['expiry_date'],
                        'batch_number' => $data['batch_number'],
                        'barcode' => $data['barcode'],
                        'uom' => $data['uom'],
                        'unit_cost' => $data['unit_cost'] ?? 0,
                        'total_cost' => $totalCost,
                    ]);
                }

                $inventory->increment('quantity', $data['quantity']);
                $this->createdCount++;
            }
        });
    }

    private function getFacilityInventory(int $productId): FacilityInventory
    {
        $inventory = FacilityInventory::where('product_id', $productId)
            ->where('facility_id', $this->facilityId)
            ->first();

        if (!$inventory) {
            $inventory = FacilityInventory::create([
                'facility_id' => $this->facilityId,
                'product_id' => $productId,
                'quantity' => 0,
            ]);
        }

        return $inventory;
    }

    private function parseUnitCost(array|Collection $row): float
    {
        $raw = $row['unit_cost'] ?? $row['Unit Cost'] ?? $row['UNIT_COST'] ?? $row['UnitCost'] ?? null;
        return ($raw !== null && $raw !== '') ? (float) $raw : 0.0;
    }

    private function parseExpiryDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $excelDate = (int) $value;
                $unixTimestamp = ($excelDate - 25569) * 86400;
                return Carbon::createFromTimestamp($unixTimestamp)->format('Y-m-d');
            }

            $value = trim((string) $value);
            try {
                return Carbon::createFromFormat('M-y', $value)->startOfMonth()->format('Y-m-d');
            } catch (\Throwable $e) {
                // continue
            }

            foreach (['d-m-Y', 'Y-m-d', 'd/m/Y', 'Y/m/d', 'm-d-Y', 'Y-m-d H:i:s', 'd-m-Y H:i:s'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $value)->format('Y-m-d');
                } catch (\Throwable $e) {
                    // continue
                }
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
