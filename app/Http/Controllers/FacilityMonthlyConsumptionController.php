<?php

namespace App\Http\Controllers;

use App\Exports\FacilityMonthlyConsumptionTemplateExport;
use App\Imports\ProcessMonthlyConsumptionImport;
use App\Models\Facility;
use App\Models\MonthlyConsumptionItem;
use App\Models\MonthlyConsumptionReport;
use App\Models\Product;
use App\Services\AmcCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class FacilityMonthlyConsumptionController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = now()->year;
        $years = range($currentYear - 3, $currentYear + 1);

        $user = auth()->user();
        $facilitiesQuery = Facility::select(['id', 'name', 'facility_type']);

        if ($user->warehouse_id && $user->warehouse->region) {
            $facilitiesQuery->where('region', trim($user->warehouse->region));
        }

        $facilities = $facilitiesQuery->orderBy('name')
            ->get()
            ->values();

        $selectedFacilityId = $request->query('facility_id');

        return Inertia::render('Inventory/FacilityMonthlyConsumption', [
            'facilities' => $facilities,
            'selectedFacilityId' => $selectedFacilityId ? (int) $selectedFacilityId : null,
            'currentYear' => $currentYear,
            'currentMonth' => now()->month,
            'yearOptions' => array_values($years),
        ]);
    }

    public function data(Request $request)
    {
        $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'year' => 'required|integer',
        ]);

        $facilityId = (int) $request->facility_id;
        $year = (int) $request->year;

        $user = auth()->user();
        $facilityQuery = Facility::select(['id', 'name', 'facility_type'])->where('id', $facilityId);
        
        if ($user->warehouse_id && $user->warehouse->region) {
            $facilityQuery->where('region', trim($user->warehouse->region));
        }
        
        $facility = $facilityQuery->first();
        if (!$facility) {
            return response()->json([
                'success' => false,
                'message' => 'Facility not found.',
            ], 422);
        }

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthKey = sprintf('%04d-%02d', $year, $m);
            $months[] = [
                'key' => $monthKey,
                'label' => now()->setDate($year, $m, 1)->format('M-y'),
            ];
        }

        $reports = MonthlyConsumptionReport::where('facility_id', $facilityId)
            ->where('month_year', 'like', $year . '-%')
            ->get();

        $reportIds = $reports->pluck('id');

        $quantityMap = [];
        if ($reportIds->isNotEmpty()) {
            $items = MonthlyConsumptionItem::with(['report'])
                ->whereIn('parent_id', $reportIds)
                ->get();

            foreach ($items as $item) {
                $report = $item->report;
                if (!$report) {
                    continue;
                }

                $pid = (int) $item->product_id;
                $monthYear = $report->month_year instanceof \Carbon\Carbon ? $report->month_year->format('Y-m') : (string) $report->getRawOriginal('month_year');

                if (!isset($quantityMap[$pid])) {
                    $quantityMap[$pid] = [];
                }

                $quantityMap[$pid][$monthYear] = (float) $item->quantity;
            }
        }

        $eligibleProducts = Product::with(['category', 'dosage'])
            ->whereHas('eligible', function ($q) use ($facility) {
                $q->where('facility_type', $facility->facility_type);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $productIds = $eligibleProducts->pluck('id')->all();
        $amcResults = app(AmcCalculationService::class)->calculateAmcForProducts($facilityId, $productIds);

        $rows = [];
        foreach ($eligibleProducts as $product) {
            $row = [
                'product_id' => $product->id,
                'item' => $product->name,
                'category' => optional($product->category)->name,
                'dosage_form' => optional($product->dosage)->name,
                'amc' => $amcResults[$product->id]['amc'] ?? 0,
                'quantities' => [],
            ];

            foreach ($months as $month) {
                $key = $month['key'];
                $row['quantities'][$key] = $quantityMap[$product->id][$key] ?? null;
            }

            $rows[] = $row;
        }

        return response()->json([
            'success' => true,
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'facility_type' => $facility->facility_type,
            ],
            'months' => $months,
            'rows' => $rows,
            'message' => empty($rows) ? 'No monthly consumption data to display for this year.' : '',
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'file' => 'required|file|mimes:xlsx,xls|max:20480', // 20MB
        ]);

        try {
            $user = auth()->user();
            $facilityId = (int) $request->facility_id;

            // SECURITY: Ensure user has access to the facility
            if ($user->warehouse_id && $user->warehouse->region) {
                $exists = Facility::where('id', $facilityId)->where('region', trim($user->warehouse->region))->exists();
                if (!$exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: Facility is outside your region.',
                    ], 403);
                }
            }
            
            $file = $request->file('file');

            $importId = (string) Str::uuid();

            Cache::put("facility_amc_import_{$importId}", [
                'status' => 'processing',
                'progress' => 0,
                'message' => 'Import started'
            ], 3600);

            $storedPath = $file->store('tmp', 'local');
            $import = new ProcessMonthlyConsumptionImport($importId, $facilityId, $storedPath);
            Excel::import($import, $storedPath);
            $results = $import->getResults();

            $message = "Import completed successfully. ";
            if ($results['imported'] > 0) {
                $message .= "Created: {$results['imported']} new records. ";
            }
            if ($results['updated'] > 0) {
                $message .= "Updated: {$results['updated']} existing records. ";
            }
            if (!empty($results['errors'])) {
                $message .= count($results['errors']) . ' errors (e.g. product not found). ';
            }

            Cache::put("facility_amc_import_{$importId}", [
                'status' => 'completed',
                'progress' => 100,
                'message' => trim($message),
                'results' => $results
            ], 3600);

            return response()->json([
                'success' => true,
                'message' => trim($message),
                'import_id' => $importId,
                'results' => $results,
                'queued' => false
            ]);

        } catch (\Throwable $e) {
            if (isset($importId)) {
                Cache::put("facility_amc_import_{$importId}", [
                    'status' => 'failed',
                    'progress' => 0,
                    'message' => 'Import failed: ' . $e->getMessage()
                ], 3600);
            }
            Log::error('Facility monthly consumption upload failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload Error: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            ], 500);
        }
    }

    public function checkImportStatus($importId)
    {
        try {
            $cacheKey = "facility_amc_import_{$importId}";
            $status = Cache::get($cacheKey);

            if (!$status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import not found or expired',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $status,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check import status: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function template(Request $request)
    {
        $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'year' => 'nullable|integer',
        ]);

        $user = auth()->user();
        $facilityId = (int) $request->facility_id;
        $facilityQuery = Facility::select(['id', 'name', 'facility_type'])->where('id', $facilityId);

        if ($user->warehouse_id && $user->warehouse->region) {
            $facilityQuery->where('region', trim($user->warehouse->region));
        }

        $facility = $facilityQuery->firstOrFail();
        $year = (int) ($request->query('year') ?? now()->year);

        $monthYears = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthYears[] = sprintf('%04d-%02d', $year, $m);
        }

        $items = Product::query()
            ->whereHas('eligible', function ($q) use ($facility) {
                $q->where('facility_type', $facility->facility_type);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $fileName = sprintf(
            'Facility_Monthly_Consumption_Template_%s_%d_%s.xlsx',
            str_replace(' ', '_', $facility->name),
            $year,
            now()->format('Y-m-d')
        );

        return Excel::download(
            new FacilityMonthlyConsumptionTemplateExport($items, $monthYears),
            $fileName
        );
    }
}

