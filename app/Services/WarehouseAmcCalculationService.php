<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\WarehouseAmc;

/**
 * Warehouse AMC (Average Monthly Consumption) Calculation Service
 * 
 * Implements the exact 70% deviation screening formula for warehouse consumption data:
 */
class WarehouseAmcCalculationService
{
    public function calculateAmc($productId, $excludeMonth = null, $warehouseId = null)
    {
        $warehouseId = $warehouseId ?: (auth()->user() ? auth()->user()->warehouse_id : null);

        // Get warehouse consumption data (last 12 months, excluding zeros)
        $query = WarehouseAmc::where('product_id', $productId)
            ->where('quantity', '>', 0) // Exclude zero quantities
            ->orderBy('month_year', 'desc') // Newest first
            ->limit(12);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $warehouseConsumptions = $query->get(['month_year', 'quantity']);

        // Convert to array for processing
        $monthsData = $warehouseConsumptions->map(function ($item) {
            return [
                'month' => $item->month_year,
                'consumption' => (float) $item->quantity
            ];
        })->toArray();

        // Exclude current month if not specified
        if ($excludeMonth === null) {
            $excludeMonth = Carbon::now()->format('Y-m');
        }

        // Filter out excluded month
        if ($excludeMonth) {
            $monthsData = array_filter($monthsData, function ($month) use ($excludeMonth) {
                return $month['month'] !== $excludeMonth;
            });
            $monthsData = array_values($monthsData); // Re-index array
        }

        return $this->processAmcCalculation($monthsData, $productId);
    }

    public function calculateAmcForProducts(array $productIds, $excludeMonth = null, $warehouseId = null)
    {
        if (empty($productIds)) {
            return [];
        }

        $warehouseId = $warehouseId ?: (auth()->user() ? auth()->user()->warehouse_id : null);

        // Exclude current month if not specified
        if ($excludeMonth === null) {
            $excludeMonth = Carbon::now()->format('Y-m');
        }

        // Calculate date range for last 13 months (12 + 1 for current month exclusion)
        $startDate = Carbon::now()->subMonths(13)->format('Y-m');
        
        // Get all warehouse consumption data for all products in one query
        $query = WarehouseAmc::whereIn('product_id', $productIds)
            ->where('quantity', '>', 0) // Exclude zero quantities
            ->where('month_year', '>=', $startDate) // Limit to last 13 months
            ->when($excludeMonth, function ($query, $excludeMonth) {
                return $query->where('month_year', '!=', $excludeMonth);
            })
            ->orderBy('product_id')
            ->orderBy('month_year', 'desc'); // Newest first

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $allConsumptions = $query->get(['product_id', 'month_year', 'quantity'])
            ->groupBy('product_id');

        $results = [];
        
        // Process each product's data
        foreach ($productIds as $productId) {
            $productConsumptions = $allConsumptions->get($productId, collect());
            
            // Limit to last 12 months and convert to array
            $monthsData = $productConsumptions->take(12)->map(function ($item) {
                return [
                    'month' => $item->month_year,
                    'consumption' => (float) $item->quantity
                ];
            })->toArray();

            $results[$productId] = $this->processAmcCalculation($monthsData, $productId);
        }
        
        return $results;
    }

    /**
     * Calculate AMC from raw monthly data using 70% deviation screening.
     * $monthsData must be ordered newest first: [['month' => 'Y-m', 'consumption' => float], ...]
     *
     * @param array $monthsData
     * @return array ['amc' => float, 'max_mc' => float, 'selectedMonths' => [], 'totalMonths' => int, 'calculation' => string]
     */
    public function calculateAmcFromMonthlyData(array $monthsData): array
    {
        return $this->processAmcCalculation($monthsData, 0);
    }

    /**
     * Process AMC calculation for a single product's consumption data
     *
     * @param array $monthsData
     * @param int $productId
     * @return array
     */
    private function processAmcCalculation($monthsData, $productId)
    {
        $monthsCount = count($monthsData);

        if ($monthsCount === 0) {
            return [
                'amc' => 0,
                'max_mc' => 0,
                'selectedMonths' => [],
                'totalMonths' => 0,
                'calculation' => 'No warehouse consumption data available'
            ];
        }

        // Apply AMC screening logic
        $selectedMonths = [];
        $amc = 0;
        $calculation = '';

        if ($monthsCount >= 3) {
            $pool = $monthsData;
            $candidates = array_splice($pool, 0, 3);
            $iteration = 0;
            $maxIterations = 10;

            while ($iteration < $maxIterations) {
                $iteration++;
                $count = count($candidates);
                if ($count === 0) {
                    if (!empty($pool)) {
                        $candidates = array_splice($pool, 0, 3);
                        continue;
                    } else {
                        break;
                    }
                }

                $avg = array_sum(array_column($candidates, 'consumption')) / $count;
                
                $passed = [];
                $failed = [];
                foreach ($candidates as $c) {
                    $dev = abs($avg - $c['consumption']);
                    $pct = $avg > 0 ? ($dev / $avg) * 100 : 0;
                    if ($pct <= 70) {
                        $passed[] = $c;
                    } else {
                        $failed[] = $c;
                    }
                }

                if (empty($failed)) {
                    // All candidates passed screening
                    $selectedMonths = $candidates;
                    $amc = $avg;
                    $calculation = "Final AMC after iteration {$iteration}. All selected months passed 70% screening. AMC = " . round($amc, 2);
                    break;
                }

                // If some failed, we keep the passed ones and refill from pool
                $candidates = $passed;
                
                // Scenario 1: If ALL failed, find the most closest two (least divergent) 
                if (empty($candidates)) {
                    // Sort all candidates by their absolute deviation from average
                    usort($failed, function($a, $b) use ($avg) {
                        return abs($avg - $a['consumption']) <=> abs($avg - $b['consumption']);
                    });
                    // Take the closest two
                    $candidates = array_slice($failed, 0, 2);
                }

                $needed = 3 - count($candidates);
                if ($needed > 0 && !empty($pool)) {
                    $added = array_splice($pool, 0, $needed);
                    $candidates = array_merge($candidates, $added);
                } else {
                    // Scenario 2: If we ran through everything and still failed to stabilize
                    // fallback to the most recent 3 months average.
                    $mostRecentThree = array_slice($monthsData, 0, 3);
                    $amc = array_sum(array_column($mostRecentThree, 'consumption')) / count($mostRecentThree);
                    $selectedMonths = $mostRecentThree;
                    $calculation = "Full 12-month screening failed to stabilize. Forced fallback to most recent 3 months average. AMC = " . round($amc, 2);
                    break;
                }
            }
        }
 elseif ($monthsCount === 2) {
            // If only 2 months available, use both (screening starts from 3 months)
            $selectedMonths = $monthsData;
            $sum = array_sum(array_column($selectedMonths, 'consumption'));
            $amc = $sum / 2;
            $calculation = "Only 2 months available, simple average applied. AMC = " . round($amc, 2);
        } elseif ($monthsCount === 1) {
            // If only 1 month available, use it
            $selectedMonths = $monthsData;
            $amc = $selectedMonths[0]['consumption'];
            $calculation = "Only 1 month available. AMC = " . round($amc, 2);
        }

        return [
            'amc' => round($amc, 2),
            'max_mc' => count($selectedMonths) > 0 ? max(array_column($selectedMonths, 'consumption')) : 0,
            'selectedMonths' => $selectedMonths,
            'totalMonths' => $monthsCount,
            'calculation' => $calculation
        ];
    }
}
