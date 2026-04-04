<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\Product;
use App\Models\FacilityMonthlyReport;
use App\Models\FacilityMonthlyReportItem;
use App\Models\FacilityInventoryMovement;
use App\Models\FacilityInventory;
use App\Models\FacilityReorderLevel;
use App\Services\AMCService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Generate LMIS report (facility_monthly_reports / facility_monthly_report_items) from facility_inventory_movements.
 * Single point of truth for both manual and automated report generation.
 */
class LmisReportFromMovementsService
{
    protected $amcService;

    public function __construct()
    {
        $this->amcService = new AMCService();
    }

    /**
     * Generate or regenerate LMIS report for a facility and month.
     * Writes to facility_monthly_reports and facility_monthly_report_items.
     *
     * @param int $facilityId
     * @param string $monthYear Format: YYYY-MM
     * @param int|null $generatedBy
     * @param bool $force
     * @return FacilityMonthlyReport
     */
    public function generate(int $facilityId, string $monthYear, ?int $generatedBy = null, bool $force = false): FacilityMonthlyReport
    {
        $facility = Facility::find($facilityId);
        if (!$facility) {
            throw new \InvalidArgumentException('Facility not found.');
        }

        // Check if report already exists
        $report = FacilityMonthlyReport::where([
            'facility_id' => $facilityId,
            'report_period' => $monthYear,
        ])->first();

        if ($report && !$force) {
            return $report->load(['items.product', 'facility', 'generatedBy']);
        }

        return DB::transaction(function () use ($facility, $monthYear, $generatedBy, $report) {
            if ($report) {
                $report->items()->delete();
                $report->update([
                    'status' => 'draft',
                    'generated_by' => $generatedBy ?? auth()->id() ?? $report->generated_by ?? 1,
                ]);
            } else {
                $report = FacilityMonthlyReport::create([
                    'facility_id' => $facility->id,
                    'report_period' => $monthYear,
                    'status' => 'draft',
                    'generated_by' => $generatedBy ?? auth()->id() ?? 1,
                ]);
            }

            $this->generateReportItems($facility, $report);

            return $report->load(['items.product.category', 'facility', 'generatedBy']);
        });
    }

    /**
     * Internal logic for generating report items (batch-level granularity)
     */
    private function generateReportItems(Facility $facility, FacilityMonthlyReport $report): void
    {
        $reportPeriod = $report->report_period;
        $startDate = Carbon::parse($reportPeriod . '-01')->startOfMonth()->startOfDay();
        $endDate = Carbon::parse($reportPeriod . '-01')->endOfMonth()->endOfDay();

        // 1. Get candidate products from current inventory, movements, or previous report
        $inventoryProductIds = DB::table('facility_inventories')
            ->where('facility_id', $facility->id)
            ->where('quantity', '>', 0)
            ->distinct()->pluck('product_id');

        $movementProductIds = FacilityInventoryMovement::where('facility_id', $facility->id)
            ->whereBetween('movement_date', [$startDate, $endDate])
            ->distinct()->pluck('product_id');

        $previousMonth = Carbon::parse($reportPeriod . '-01')->subMonth()->format('Y-m');
        $previousReport = FacilityMonthlyReport::where(['facility_id' => $facility->id, 'report_period' => $previousMonth])->first();
        $prevReportProductIds = $previousReport ? 
            FacilityMonthlyReportItem::where('parent_id', $previousReport->id)->distinct()->pluck('product_id') : 
            collect();

        $eligibleProductIds = $facility->eligibleProducts()->pluck('products.id');

        // Merge all discovered products
        $allProductIds = $inventoryProductIds
            ->merge($movementProductIds)
            ->merge($prevReportProductIds)
            ->merge($eligibleProductIds)
            ->unique();

        // 2. Load detail data in bulk
        $detailedMovements = FacilityInventoryMovement::where('facility_id', $facility->id)
            ->whereBetween('movement_date', [$startDate, $endDate])
            ->orderBy('movement_date')
            ->get();

        $detailedBatchLookup = $detailedMovements->groupBy(function($m) {
            return $m->product_id . '_' . ($m->batch_number ?? 'no-batch') . '_' . ($m->expiry_date ?? 'no-expiry');
        });

        $reorderLevels = FacilityReorderLevel::where('facility_id', $facility->id)
            ->whereIn('product_id', $allProductIds)
            ->pluck('amc', 'product_id')
            ->toArray();

        // 3. Process each product
        foreach ($allProductIds as $productId) {
            try {
                $product = Product::find($productId);
                if (!$product) continue;

                // Calculate Product-Level aggregates (AMC)
                $amcResult = $this->amcService->calculateScreenedAMC($facility->id, $product->id, 12, $reportPeriod);
                $productAmc = (float) ($amcResult['amc'] ?? 0);
                if ($productAmc <= 0 && isset($reorderLevels[$product->id]) && $reorderLevels[$product->id] > 0) {
                    $productAmc = (float) $reorderLevels[$product->id];
                }

                // Identify all batches for this product
                $productBatches = [];

                // From movements in this period
                foreach ($detailedBatchLookup as $key => $moms) {
                    if (strpos($key, $productId . '_') === 0) {
                        $m = $moms->first();
                        $bKey = ($m->batch_number ?? 'no-batch') . '_' . ($m->expiry_date ?? 'no-expiry');
                        $productBatches[$bKey] = [
                            'batch_number' => $m->batch_number,
                            'expiry_date' => $m->expiry_date,
                            'movements' => $moms
                        ];
                    }
                }

                // From previous month's report
                if ($previousReport) {
                    $prevItems = FacilityMonthlyReportItem::where(['parent_id' => $previousReport->id, 'product_id' => $productId])->get();
                    foreach ($prevItems as $pi) {
                        $bKey = ($pi->batch_number ?? 'no-batch') . '_' . ($pi->expiry_date ? $pi->expiry_date->format('Y-m-d') : 'no-expiry');
                        if (!isset($productBatches[$bKey])) {
                            $productBatches[$bKey] = [
                                'batch_number' => $pi->batch_number,
                                'expiry_date' => $pi->expiry_date ? $pi->expiry_date->format('Y-m-d') : null,
                                'movements' => collect()
                            ];
                        }
                    }
                }

                // Default row if no batches found
                if (empty($productBatches)) {
                    $productBatches['no-batch_no-expiry'] = [
                        'batch_number' => null,
                        'expiry_date' => null,
                        'movements' => collect()
                    ];
                }

                // Calculate balances per batch, and total product closing balance
                $productTotalClosing = 0;
                $batchDataRows = [];

                foreach ($productBatches as $batchInfo) {
                    $batchMovements = $batchInfo['movements'];
                    $openingBalance = $this->calculateOpeningBalance($facility, $product, $batchInfo['batch_number'], $batchInfo['expiry_date'], $reportPeriod);
                    
                    $received = (float) $batchMovements->sum('facility_received_quantity');
                    $issued = (float) $batchMovements->sum('facility_issued_quantity');
                    
                    // Future: adjustments query can be added here
                    $posAdj = 0.0;
                    $negAdj = 0.0;
                    
                    $closing = $openingBalance + $received - $issued + $posAdj - $negAdj;
                    $productTotalClosing += $closing;

                    $batchDataRows[] = [
                        'batch_number' => $batchInfo['batch_number'],
                        'expiry_date' => $batchInfo['expiry_date'],
                        'opening_balance' => max(0, $openingBalance),
                        'stock_received' => $received,
                        'stock_issued' => $issued,
                        'positive_adjustments' => $posAdj,
                        'negative_adjustments' => $negAdj,
                        'closing_balance' => max(0, $closing),
                        'movements' => $batchMovements
                    ];
                }

                // Create the report items
                foreach ($batchDataRows as $row) {
                    $mos = $productAmc > 0 ? (string) round($productTotalClosing / $productAmc, 1) : null;
                    $breakdown = $this->prepareQuantityBreakdown($row['movements']);

                    FacilityMonthlyReportItem::create([
                        'parent_id' => $report->id,
                        'product_id' => $product->id,
                        'batch_number' => $row['batch_number'],
                        'expiry_date' => $row['expiry_date'],
                        'opening_balance' => $row['opening_balance'],
                        'stock_received' => $row['stock_received'],
                        'stock_issued' => $row['stock_issued'],
                        'positive_adjustments' => $row['positive_adjustments'],
                        'negative_adjustments' => $row['negative_adjustments'],
                        'closing_balance' => $row['closing_balance'],
                        'total_closing_balance' => $productTotalClosing,
                        'average_monthly_consumption' => $productAmc,
                        'months_of_stock' => $mos,
                        'stockout_days' => $this->calculateStockoutDays($facility, $product),
                        'quantity_breakdown' => json_encode($breakdown),
                        'movement_count' => $row['movements']->count(),
                        'received_transactions' => $row['movements']->where('facility_received_quantity', '>', 0)->count(),
                        'issued_transactions' => $row['movements']->where('facility_issued_quantity', '>', 0)->count(),
                    ]);
                }

            } catch (\Exception $e) {
                Log::error("Error processing product {$productId} in report service: " . $e->getMessage());
                continue;
            }
        }
    }

    private function calculateOpeningBalance(Facility $facility, Product $product, ?string $batchNumber, ?string $expiryDate, string $reportPeriod): float
    {
        $prevMonth = Carbon::parse($reportPeriod . '-01')->subMonth()->format('Y-m');
        $previousReport = FacilityMonthlyReport::where(['facility_id' => $facility->id, 'report_period' => $prevMonth])->first();

        if ($previousReport) {
            $query = FacilityMonthlyReportItem::where(['parent_id' => $previousReport->id, 'product_id' => $product->id]);

            if ($batchNumber && $batchNumber !== 'no-batch') {
                $query->where('batch_number', $batchNumber);
            } else {
                $query->where(function($q) { $q->whereNull('batch_number')->orWhere('batch_number', 'no-batch'); });
            }

            if ($expiryDate && $expiryDate !== 'no-expiry') {
                $query->whereDate('expiry_date', $expiryDate);
            } else {
                $query->whereNull('expiry_date');
            }

            $item = $query->first();
            return $item ? (float) $item->closing_balance : 0.0;
        }

        return 0.0;
    }

    private function calculateStockoutDays(Facility $facility, Product $product): int
    {
        $inventory = FacilityInventory::where(['facility_id' => $facility->id, 'product_id' => $product->id])->first();
        if (!$inventory || $inventory->quantity <= 0) {
            return 0; // Simplified for now
        }
        return 0;
    }

    private function prepareQuantityBreakdown($movements): array
    {
        $breakdown = [
            'total_movements' => $movements->count(),
            'received_breakdown' => [],
            'issued_breakdown' => [],
            'daily_totals' => [],
        ];

        foreach ($movements as $m) {
            $date = Carbon::parse($m->movement_date)->format('Y-m-d');
            if (!isset($breakdown['daily_totals'][$date])) {
                $breakdown['daily_totals'][$date] = ['received' => 0, 'issued' => 0];
            }

            if ($m->facility_received_quantity > 0) {
                $breakdown['received_breakdown'][] = [
                    'date' => $m->movement_date,
                    'quantity' => $m->facility_received_quantity,
                    'movement_type' => $m->movement_type,
                ];
                $breakdown['daily_totals'][$date]['received'] += $m->facility_received_quantity;
            }

            if ($m->facility_issued_quantity > 0) {
                $breakdown['issued_breakdown'][] = [
                    'date' => $m->movement_date,
                    'quantity' => $m->facility_issued_quantity,
                    'movement_type' => $m->movement_type,
                ];
                $breakdown['daily_totals'][$date]['issued'] += $m->facility_issued_quantity;
            }
        }

        return $breakdown;
    }
}
