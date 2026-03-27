<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExpiredResource;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Disposal;
use App\Models\DisposalItem;
use App\Models\User;
use App\Models\Transfer;
use App\Models\Facility;
use App\Models\Category;
use App\Models\Dosage;
use App\Models\Reason;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Notifications\DisposalActionRequired;

class ExpiredController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('expiry-view')) {
            abort(403, 'Unauthorized. You do not have permission to view the Expiry module.');
        }
        // Use same date logic as Expiry Report (ReportController::getExpiryReportData)
        $today = Carbon::today()->startOfDay();
        $sixMonthsFromNow = $today->copy()->addMonths(6);
        $oneYearFromNow = $today->copy()->addYear();
    
        $query = InventoryItem::query();
    
        $query->with(['product.dosage:id,name', 'product.category:id,name', 'warehouse']);
    
        $user = auth()->user();
        if ($user->warehouse_id) {
            $query->where('warehouse_id', $user->warehouse_id);
        }
    
        $query->where('quantity', '>', 0)
              ->where(function($q) use ($today, $oneYearFromNow) {
                  $q->where('expiry_date', '<=', $oneYearFromNow)
                    ->orWhere('expiry_date', '<', $today);
              });
    
        // Filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('barcode', 'like', "%{$search}%")
                  ->orWhere('batch_number', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($prodQ) use ($search) {
                      $prodQ->where('name', 'like', "%{$search}%");
                  });
            });
        }
    
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }
    
        if ($request->filled('category')) {
            $query->whereHas('product.category', function ($q) use ($request) {
                $q->where('name', $request->category);
            });
        }
    
        if ($request->filled('dosage')) {
            $query->whereHas('product.dosage', function ($q) use ($request) {
                $q->where('name', $request->dosage);
            });
        }
    
        if ($request->filled('warehouse')) {
            $query->whereHas('warehouse', function($q) use($request){
                $q->where('name', 'like', "%{$request->warehouse}%");
            });
        }
    
        if ($request->filled('location')) {
            $query->where('location', 'like', "%{$request->location}%");
        }

        // Expiry status filter - must match Status labels in frontend (180 and 365 days)
        if ($request->filled('expiry_status')) {
            $status = $request->expiry_status;
            $in180Days = $today->copy()->addDays(180);
            $in365Days = $today->copy()->addDays(365);
            if ($status === 'expired') {
                $query->where('expiry_date', '<', $today);
            } elseif ($status === 'expiring_very_soon') {
                // 0 < days <= 180 - matches "Expiring Very Soon" badge
                $query->where('expiry_date', '>', $today)
                      ->where('expiry_date', '<=', $in180Days);
            } elseif ($status === 'expiring_soon') {
                // 180 < days <= 365 - matches "Expiring Soon" badge
                $query->where('expiry_date', '>', $in180Days)
                      ->where('expiry_date', '<=', $in365Days);
            }
        }

        // Tab filtering - same logic as Expiry Report
        if ($request->filled('tab')) {
            $tab = $request->tab;
            if ($tab === 'expired') {
                $query->where('expiry_date', '<', $today);
            } elseif ($tab === 'six_months') {
                $query->where('expiry_date', '>=', $today)
                      ->where('expiry_date', '<=', $sixMonthsFromNow);
            } elseif ($tab === 'year') {
                $query->where('expiry_date', '>', $sixMonthsFromNow)
                      ->where('expiry_date', '<=', $oneYearFromNow);
            }
            // 'all' tab shows everything (no additional filtering)
        }
    
        // Paginate while still a query builder
        $paginatedInventories = $query->paginate(
            $request->input('per_page', 25),
            ['*'],
            'page',
            $request->input('page', 1)
        )->withQueryString();
    
        $paginatedInventories->setPath(url()->current());
    
        // Map inventory data for expiry-related flags (same logic as Expiry Report)
        $paginatedInventories->getCollection()->transform(function($inventory) use ($today) {
            $inventory->expiry_date = Carbon::parse($inventory->expiry_date);
            $inventory->expired = $inventory->expiry_date->lt($today);
            $inventory->days_until_expiry = intval($today->diffInDays($inventory->expiry_date, false));
            $inventory->disposed = (bool) $inventory->disposed;
            // Expiring Very Soon: 0-6 months; Expiring Soon: 6-12 months (align with Report)
            $inventory->expiring_soon = !$inventory->expired && $inventory->days_until_expiry <= 180;
            return $inventory;
        });
    
        $products = Product::select('id', 'name')->get();
        $warehouses = Warehouse::pluck('name')->toArray();
        $category = Category::pluck('name')->toArray();
        $dosage = Dosage::pluck('name')->toArray();
        
        // Load locations based on selected warehouse
        $locations = [];
        if ($request->filled('warehouse')) {
            $locations = Location::where('warehouse', $request->warehouse)->pluck('location')->toArray();
        }
    
        // Calculate summary based on current tab filter
        $summary = [];
        if ($request->filled('tab')) {
            $tab = $request->tab;
            if ($tab === 'expired') {
                $summary = [
                    'total' => $paginatedInventories->total(),
                    'expiring_within_6_months' => 0,
                    'expiring_within_1_year' => 0,
                    'expired' => $paginatedInventories->total(),
                    'disposed' => 0,
                ];
            } elseif ($tab === 'six_months') {
                $summary = [
                    'total' => $paginatedInventories->total(),
                    'expiring_within_6_months' => $paginatedInventories->total(),
                    'expiring_within_1_year' => 0,
                    'expired' => 0,
                    'disposed' => 0,
                ];
            } elseif ($tab === 'year') {
                $summary = [
                    'total' => $paginatedInventories->total(),
                    'expiring_within_6_months' => 0,
                    'expiring_within_1_year' => $paginatedInventories->total(),
                    'expired' => 0,
                    'disposed' => 0,
                ];
            }
        } else {
            // For 'all' tab or no tab specified, we need to get the full dataset for accurate summary
            // Create a separate query for summary calculation without pagination
            $summaryQuery = InventoryItem::query();
            $summaryQuery->with(['product.dosage:id,name', 'product.category:id,name', 'warehouse']);
            
            $user = auth()->user();
            if ($user->warehouse_id) {
                $summaryQuery->where('warehouse_id', $user->warehouse_id);
            }
            
            $summaryQuery->where('quantity', '>', 0)
                  ->where(function($q) use ($today, $oneYearFromNow) {
                      $q->where('expiry_date', '<=', $oneYearFromNow)
                        ->orWhere('expiry_date', '<', $today);
                  });

            // Apply the same filters as the main query (except pagination)
            if ($request->filled('search')) {
                $search = $request->search;
                $summaryQuery->where(function ($q) use ($search) {
                    $q->where('barcode', 'like', "%{$search}%")
                      ->orWhere('batch_number', 'like', "%{$search}%")
                      ->orWhereHas('product', function ($prodQ) use ($search) {
                          $prodQ->where('name', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->filled('product_id')) {
                $summaryQuery->where('product_id', $request->product_id);
            }

            if ($request->filled('category')) {
                $summaryQuery->whereHas('product.category', function ($q) use ($request) {
                    $q->where('name', $request->category);
                });
            }

            if ($request->filled('dosage')) {
                $summaryQuery->whereHas('product.dosage', function ($q) use ($request) {
                    $q->where('name', $request->dosage);
                });
            }

            if ($request->filled('warehouse')) {
                $summaryQuery->whereHas('warehouse', function($q) use($request){
                    $q->where('name', 'like', "%{$request->warehouse}%");
                });
            }

            if ($request->filled('location')) {
                $summaryQuery->where('location', 'like', "%{$request->location}%");
            }

            if ($request->filled('expiry_status')) {
                $status = $request->expiry_status;
                $in180Days = $today->copy()->addDays(180);
                $in365Days = $today->copy()->addDays(365);
                if ($status === 'expired') {
                    $summaryQuery->where('expiry_date', '<', $today);
                } elseif ($status === 'expiring_very_soon') {
                    $summaryQuery->where('expiry_date', '>', $today)
                          ->where('expiry_date', '<=', $in180Days);
                } elseif ($status === 'expiring_soon') {
                    $summaryQuery->where('expiry_date', '>', $in180Days)
                          ->where('expiry_date', '<=', $in365Days);
                }
            }

            // Summary counts - use 180/365 days to match Status labels (Expiring Very Soon ≤180, Expiring Soon 181–365)
            $in180Days = $today->copy()->addDays(180);
            $in365Days = $today->copy()->addDays(365);
            $expiredCount = (clone $summaryQuery)->where('expiry_date', '<', $today)->count();
            $within6Count = (clone $summaryQuery)->where('expiry_date', '>', $today)->where('expiry_date', '<=', $in180Days)->count();
            $within1YearCount = (clone $summaryQuery)->where('expiry_date', '>', $in180Days)->where('expiry_date', '<=', $in365Days)->count();

            $summary = [
                'total' => $expiredCount + $within6Count + $within1YearCount,
                'expiring_within_6_months' => $within6Count,
                'expiring_within_1_year' => $within1YearCount,
                'expired' => $expiredCount,
                'disposed' => 0,
            ];
        }

        return inertia('Expired/Index', [
            'inventories' => ExpiredResource::collection($paginatedInventories),
            'summary' => $summary,
            'products' => $products,
            'warehouses' => $warehouses,
            'categories' => $category,
            'dosage' => $dosage,
            'locations' => $locations,
            'filters' => $request->only('search', 'product_id', 'warehouse', 'dosage', 'category', 'location', 'batch_number', 'expiry_date_from', 'expiry_date_to', 'per_page', 'page', 'tab', 'expiry_status'),
        ]);
    }

    public function dispose(Request $request)
    {
        if (!auth()->user()->hasPermission('expiry-view')) {
            abort(403, 'Unauthorized. You do not have permission to perform disposal in the Expiry module.');
        }
        try {
            // Validate the request
            $validated = $request->validate([
                'id' => 'required|exists:inventory_items,id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
                'note' => 'nullable|string|max:255',
                'type' => 'nullable|string',
                'attachments' => 'nullable|array',
                'attachments.*' => 'nullable|file|mimes:pdf', // Max 10MB per file
            ]);
            
            // Start a database transaction
            DB::beginTransaction();
            
            // Get the inventory to include its number in the note
            $inventory = InventoryItem::with('warehouse')->find($request->id);
            
            // SECURITY: Ensure user owns the inventory item being disposed
            if (auth()->user()->warehouse_id && (int)$inventory->warehouse_id !== (int)auth()->user()->warehouse_id) {
                abort(403, 'Unauthorized access to this inventory item.');
            }
            
            // Generate note based on condition and source
            $note = "FROM INVENTORY";
            if ($request->note && $request->note !== 'undefined' && trim($request->note) !== '') {
                $note .= " - {$request->note}";
            }
            
            // Handle file attachments if any
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $index => $file) {
                    if ($file->isValid()) {
                        $fileName = 'disposal_' . time() . '_' . $index . '.' . $file->getClientOriginalExtension();
                        $file->move(public_path('attachments/disposals'), $fileName);
                        $attachments[] = [
                            'name' => $file->getClientOriginalName(),
                            'path' => '/attachments/disposals/' . $fileName,
                            'type' => $file->getClientMimeType(),
                            'size' => filesize(public_path('attachments/disposals/' . $fileName)),
                            'uploaded_at' => now()->toDateTimeString()
                        ];
                    }
                }
            }
            
            $warehouseName = $inventory->warehouse->name ?? null;

            // Create disposal record with source 'Expiry' so it appears in the wastage disposal list/detail
            $disposal = Disposal::create([
                'disposed_by' => auth()->id(),
                'disposed_at' => Carbon::now(),
                'status' => 'pending',
                'source' => 'Expiry',
                'warehouse' => $inventory->location,
            ]);

            // Create disposal item so detail page shows items and total
            $unitCost = $inventory->unit_cost ?? 0;
            $totalCost = $unitCost * (int) $request->quantity;

            DisposalItem::create([
                'disposal_id' => $disposal->id,
                'product_id' => $request->product_id,
                'quantity' => (int) $request->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'barcode' => $inventory->barcode,
                'expire_date' => $inventory->expiry_date,
                'batch_number' => $inventory->batch_number,
                'uom' => $inventory->uom,
                'location' => $inventory->location,
                'warehouse' => $warehouseName,
                'note' => $note,
                'type' => $request->type ?? 'Expired',
                'attachments' => !empty($attachments) ? $attachments : null,
            ]);

            // Notify users with disposal-review permission (next action = review)
            $reviewers = User::withPermission('disposal-review')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            foreach ($reviewers as $user) {
                $user->notify(new DisposalActionRequired($disposal, DisposalActionRequired::ACTION_NEEDS_REVIEW));
            }

            // Remove from inventory
            $inventory->delete();
            
            // Commit the transaction
            DB::commit();
            
            return response()->json('Item has been disposed successfully', 200);
        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function transfer(Request $request, $inventory)
    {
        if (!auth()->user()->hasPermission('expiry-view')) {
            abort(403, 'Unauthorized. You do not have permission to perform transfer in the Expiry module.');
        }
        if ($request->isMethod('get')) {
            $inv = InventoryItem::with('product','warehouse')->find($inventory);
            
            if (!$inv) {
                return redirect()->route('expired.index')->with('error', 'Inventory not found');
            }

            // SECURITY: Ensure user owns the inventory item being transferred
            if (auth()->user()->warehouse_id && (int)$inv->warehouse_id !== (int)auth()->user()->warehouse_id) {
                abort(403, 'Unauthorized access to this inventory item.');
            }

            $facilities = Facility::with('eligibleProducts:id')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name ?? '',
                    'eligible_product_ids' => $f->eligibleProducts->pluck('id')->toArray(),
                ])
                ->values()
                ->toArray();
            $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get()
                ->map(fn ($w) => ['id' => $w->id, 'name' => $w->name ?? ''])
                ->values()
                ->toArray();
            $transferID = Transfer::generateTransferId();

            return inertia("Expired/Transfer", [
                "inventory" => $inv,
                'facilities' => $facilities,
                'warehouses' => $warehouses,
                'transferID' => $transferID,
                'reasons' => Reason::pluck('name')->toArray()
            ]);
        }

        $request->validate([
            'destination_type' => 'required|in:warehouse,facility',
            'destination_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            $inventory = InventoryItem::findOrFail($inventory);

            if ($request->destination_type === 'facility') {
                $facility = Facility::with('eligibleProducts:id')->find($request->destination_id);
                $eligibleIds = $facility ? $facility->eligibleProducts->pluck('id')->toArray() : [];
                $productId = $inventory->product_id ? (int) $inventory->product_id : null;
                if ($productId === null || !in_array($productId, $eligibleIds, true)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'The destination facility can only receive products that are eligible for its facility type. This item is not eligible.',
                    ], 422);
                }
                
                // SECURITY: If regional user, ensure facility belongs to their region
                if (auth()->user()->warehouse_id && auth()->user()->warehouse->region) {
                    if (trim($facility->region) !== trim(auth()->user()->warehouse->region)) {
                        DB::rollBack();
                        return response()->json(['message' => 'Unauthorized facility selection.'], 403);
                    }
                }
            }

            if ($request->quantity > $inventory->quantity) {
                return response()->json([
                    'message' => 'Transfer quantity cannot exceed available quantity'
                ], 422);
            }

            // Generate transfer ID
            $transferId = Transfer::generateTransferId();

            // Create transfer record
            $transfer = Transfer::create([
                'transfer_id' => $transferId,
                'inventory_id' => $inventory->id,
                'destination_type' => $request->destination_type,
                'destination_id' => $request->destination_id,
                'quantity' => $request->quantity,
                'notes' => $request->notes,
                'transferred_by' => Auth::id(),
                'status' => 'completed'
            ]);

            // Update inventory quantity
            $inventory->update([
                'quantity' => $inventory->quantity - $request->quantity
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transfer completed successfully',
                'transfer_id' => $transferId
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to process transfer. ' . $e->getMessage()
            ], 500);
        }
    }
}