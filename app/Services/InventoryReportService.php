<?php

namespace App\Services;

use App\Models\InventoryReport;
use App\Models\InventoryReportItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\MonthlyQuantityReceived;
use App\Models\IssueQuantityReport;
use App\Models\ReceivedQuantity;
use App\Models\IssuedQuantity;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class InventoryReportService
{
    /**
     * Generate or update inventory reports for a given month and warehouse(s).
     *
     * @param string|null $monthYear YYYY-MM format
     * @param int|null $warehouseId
     * @param bool $force
     * @return array Summary of generation
     */
    public function generate($monthYear = null, $warehouseId = null, $force = false)
    {
        $monthYear = $monthYear ?? Carbon::now()->format('Y-m');
        $date = Carbon::createFromFormat('Y-m', $monthYear);
        $previousDate = $date->copy()->subMonth();
        $previousMonthYear = $previousDate->format('Y-m');

        $warehouses = $warehouseId 
            ? Warehouse::where('id', $warehouseId)->get() 
            : Warehouse::all();

        $results = [
            'month_year' => $monthYear,
            'processed' => 0,
            'items_generated' => 0,
            'skipped' => 0,
            'errors' => [],
            'first_report' => null
        ];

        // Ensure we only process warehouses with valid IDs
        $warehouses = $warehouses->filter(function($w) {
            if (empty($w->id)) {
                Log::warning("InventoryReportService: Skipping warehouse with empty ID: " . ($w->name ?? 'Unknown'));
                return false;
            }
            return true;
        });

        foreach ($warehouses as $warehouse) {
            try {
                $report = $this->getOrCreateReport($monthYear, $warehouse->id, $force);
                
                if (!$report) {
                    $results['skipped']++;
                    continue;
                }

                if (!$results['first_report']) {
                    $results['first_report'] = $report;
                }

                $itemsCount = $this->generateReportItems($report, $date, $previousDate, $warehouse->id);
                $results['processed']++;
                $results['items_generated'] += $itemsCount;

            } catch (\Exception $e) {
                $results['errors'][] = "Warehouse {$warehouse->id}: " . $e->getMessage();
                Log::error("InventoryReportService Error for Warehouse {$warehouse->id}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Get existing or create new report for a warehouse/month.
     */
    private function getOrCreateReport($monthYear, $warehouseId, $force)
    {
        if (empty($warehouseId)) {
            Log::error("InventoryReportService: Attempted to get/create report with empty warehouseId for month {$monthYear}");
            return null;
        }

        $report = InventoryReport::where('month_year', $monthYear)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($report && !$force) {
            return null; // Skip if exists and not forced
        }

        if ($report && $force) {
            $report->items()->delete();
        } else {
            $report = new InventoryReport();
            $report->month_year = $monthYear;
            $report->warehouse_id = $warehouseId;
            $report->status = 'pending';
        }

        $report->generated_by = auth()->id() ?? 1; // Default to system user
        $report->generated_at = now();
        $report->save();

        return $report;
    }

    /**
     * Generate items for a specific report.
     */
    private function generateReportItems(InventoryReport $report, Carbon $date, Carbon $previousDate, int $warehouseId)
    {
        $startOfMonth = $date->copy()->startOfMonth()->startOfDay();
        $endOfMonth = $date->copy()->endOfMonth()->endOfDay();
        $prevMonthYear = $previousDate->format('Y-m');

        // Identify all product-batch combinations that had activity or had a balance
        $allBatchKeys = $this->getRelevantProductBatches($warehouseId, $startOfMonth, $endOfMonth, $prevMonthYear);

        $itemsGenerated = 0;
        foreach ($allBatchKeys as $row) {
            $product = Product::with('dosage')->find($row['product_id']);
            if (!$product) continue;

            $batchNumber = ($row['batch_key'] === 'N/A') ? null : $row['batch_key'];
            
            if ($this->generateItem($report, $product, $warehouseId, $batchNumber, $startOfMonth, $endOfMonth, $prevMonthYear)) {
                $itemsGenerated++;
            }
        }

        return $itemsGenerated;
    }

    /**
     * Get unique product-batch keys that are relevant for this report.
     */
    private function getRelevantProductBatches(int $warehouseId, Carbon $start, Carbon $end, string $prevMonthYear)
    {
        // 1. Batches received this month
        $received = ReceivedQuantity::whereBetween('received_at', [$start, $end])
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('product_id, COALESCE(NULLIF(TRIM(batch_number), ""), "N/A") as batch_key')
            ->groupBy('product_id', 'batch_key')
            ->get();

        // 2. Batches issued this month
        $issued = IssuedQuantity::whereBetween('issued_date', [$start, $end])
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('product_id, COALESCE(NULLIF(TRIM(batch_number), ""), "N/A") as batch_key')
            ->groupBy('product_id', 'batch_key')
            ->get();

        // 3. Batches that had a closing balance last month
        $previous = InventoryReportItem::whereHas('report', fn($q) => $q->where('month_year', $prevMonthYear))
            ->where('warehouse_id', $warehouseId)
            ->where('closing_balance', '>', 0)
            ->selectRaw('product_id, COALESCE(NULLIF(TRIM(batch_number), ""), "N/A") as batch_key')
            ->groupBy('product_id', 'batch_key')
            ->get();

        return collect($received)->merge($issued)->merge($previous)
            ->unique(fn($i) => $i['product_id'] . '-' . $i['batch_key']);
    }

    /**
     * Calculate and save a single report item.
     */
    private function generateItem(InventoryReport $report, Product $product, int $warehouseId, ?string $batchNumber, Carbon $start, Carbon $end, string $prevMonthYear)
    {
        $applyBatchFilter = function ($q) use ($batchNumber) {
            if ($batchNumber) {
                $q->whereRaw('TRIM(COALESCE(batch_number, "")) = ?', [trim($batchNumber)]);
            } else {
                $q->where(fn($sub) => $sub->whereNull('batch_number')->orWhere('batch_number', '')->orWhereRaw("TRIM(COALESCE(batch_number, '')) = ''"));
            }
        };

        // Calculations
        $receivedQuantity = (int) ReceivedQuantity::whereBetween('received_at', [$start, $end])
            ->where('product_id', $product->id)->where('warehouse_id', $warehouseId)
            ->where($applyBatchFilter)->sum('quantity');

        $issuedQuantity = (int) IssuedQuantity::whereBetween('issued_date', [$start, $end])
            ->where('product_id', $product->id)->where('warehouse_id', $warehouseId)
            ->where($applyBatchFilter)->sum('quantity');

        $beginningBalance = (int) $this->getPreviousClosingBalance($product->id, $warehouseId, $prevMonthYear, $batchNumber);

        // Adjustments
        $adjustments = $this->calculateAdjustments($product->id, $warehouseId, $batchNumber, $start, $end);
        
        $closingBalance = $beginningBalance + $receivedQuantity - $issuedQuantity - $adjustments['neg'] + $adjustments['pos'];

        // Only save if there's any data
        if ($beginningBalance == 0 && $receivedQuantity == 0 && $issuedQuantity == 0 && $closingBalance == 0 && $adjustments['neg'] == 0 && $adjustments['pos'] == 0) {
            return false;
        }

        // Unit Cost Logic
        $unitCost = $this->resolveUnitCost($product->id, $warehouseId, $batchNumber, $start, $end, $prevMonthYear);

        $attrs = [
            'inventory_report_id' => $report->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'batch_number' => $batchNumber ?? 'N/A',
            'beginning_balance' => $beginningBalance,
            'received_quantity' => $receivedQuantity,
            'issued_quantity' => $issuedQuantity,
            'negative_adjustment' => $adjustments['neg'],
            'positive_adjustment' => $adjustments['pos'],
            'closing_balance' => $closingBalance,
            'total_closing_balance' => $closingBalance,
            'unit_cost' => round($unitCost, 2),
            'total_cost' => round(abs($closingBalance) * $unitCost, 2),
            'average_monthly_consumption' => $this->calculateAMC($product->id, $warehouseId, $start),
            'stockout_days' => $this->calculateStockoutDays($product->id, $start, $end),
            'uom' => $product->dosage?->name ?? $product->uom ?? 'pcs',
            'expiry_date' => $this->resolveExpiryDate($product, $batchNumber, $start, $end)
        ];

        InventoryReportItem::create($attrs);
        return true;
    }

    private function getPreviousClosingBalance($productId, $warehouseId, $prevMonthYear, $batchNumber)
    {
        $query = InventoryReportItem::whereHas('report', fn($q) => $q->where('month_year', $prevMonthYear))
            ->where('product_id', $productId)->where('warehouse_id', $warehouseId);

        if ($batchNumber) {
            $query->whereRaw('TRIM(COALESCE(batch_number, "")) = ?', [trim($batchNumber)]);
        } else {
            $query->where(fn($q) => $q->whereNull('batch_number')->orWhere('batch_number', '')->orWhere('batch_number', 'N/A'));
        }

        return $query->value('closing_balance') ?? 0;
    }

    private function calculateAdjustments($productId, $warehouseId, $batchNumber, Carbon $start, Carbon $end)
    {
        $query = InventoryAdjustmentItem::whereHas('adjustment', function($q) use ($start, $end, $warehouseId) {
            $q->where('status', 'approved')
              ->whereBetween('adjustment_date', [$start->toDateString(), $end->toDateString()])
              ->where('warehouse_id', $warehouseId);
        })->where('product_id', $productId);

        if ($batchNumber) {
            $query->where('batch_number', $batchNumber);
        } else {
            $query->where(fn($q) => $q->whereNull('batch_number')->orWhere('batch_number', ''));
        }

        return [
            'pos' => (int) (clone $query)->where('difference', '>', 0)->sum('difference'),
            'neg' => (int) abs((clone $query)->where('difference', '<', 0)->sum('difference'))
        ];
    }

    private function resolveUnitCost($productId, $warehouseId, $batchNumber, $start, $end, $prevMonthYear)
    {
        // 1. Try from received items this month
        $received = ReceivedQuantity::whereBetween('received_at', [$start, $end])
            ->where('product_id', $productId)->where('warehouse_id', $warehouseId)
            ->whereRaw('TRIM(COALESCE(batch_number, "")) = ?', [trim($batchNumber ?? '')])
            ->selectRaw('SUM(total_cost) as total_cost, SUM(quantity) as qty')
            ->first();

        if ($received && $received->qty > 0) return $received->total_cost / $received->qty;

        // 2. Try from previous month report
        $prev = InventoryReportItem::whereHas('report', fn($q) => $q->where('month_year', $prevMonthYear))
            ->where('product_id', $productId)->where('warehouse_id', $warehouseId)
            ->whereRaw('TRIM(COALESCE(batch_number, "")) = ?', [trim($batchNumber ?? '')])
            ->value('unit_cost');

        return $prev ?? 0;
    }

    private function calculateAMC($productId, $warehouseId, Carbon $start)
    {
        // Simple logic: last 3 months average issued quantity
        $threeMonthsAgo = $start->copy()->subMonths(3);
        $totalIssued = IssuedQuantity::whereBetween('issued_date', [$threeMonthsAgo, $start->copy()->subSecond()])
            ->where('product_id', $productId)->where('warehouse_id', $warehouseId)
            ->sum('quantity');

        return round($totalIssued / 3, 0);
    }

    private function calculateStockoutDays($productId, Carbon $start, Carbon $end)
    {
        // Simplified: Days from last issue to next receive or end of month
        $lastIssue = IssuedQuantity::where('product_id', $productId)
            ->whereBetween('issued_date', [$start, $end])
            ->orderByDesc('issued_date')->value('issued_date');

        if (!$lastIssue) return 0;
        
        $lastIssueDate = Carbon::parse($lastIssue)->startOfDay();
        $nextReceive = ReceivedQuantity::where('product_id', $productId)
            ->where('received_at', '>', $lastIssueDate->endOfDay())
            ->orderBy('received_at')->value('received_at');

        $endDate = $nextReceive ? Carbon::parse($nextReceive)->startOfDay() : $end->copy()->startOfDay();
        return (int) $lastIssueDate->diffInDays($endDate);
    }

    private function resolveExpiryDate($product, $batchNumber, $start, $end)
    {
        $firstRec = ReceivedQuantity::where('product_id', $product->id)
            ->whereRaw('TRIM(COALESCE(batch_number, "")) = ?', [trim($batchNumber ?? '')])
            ->whereNotNull('expiry_date')->orderBy('received_at')->first();

        return $firstRec?->expiry_date ?? $product->expiry_date;
    }
}
