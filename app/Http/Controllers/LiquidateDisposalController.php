<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Disposal;
use App\Models\Liquidate;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\DisposalResource;
use App\Http\Resources\LiquidateResource;
use App\Notifications\LiquidationActionRequired;
use App\Notifications\DisposalActionRequired;

class LiquidateDisposalController extends Controller
{
    /**
     * Main index method for the tabbed interface
     */
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('wastage-view')) {
            abort(403, 'You do not have permission to view wastages.');
        }

        // Default date range to current month when not provided (keeps list fast as data grows)
        $dateFrom = $request->filled('date_from') ? $request->date_from : now()->startOfMonth()->format('Y-m-d');
        $dateTo = $request->filled('date_to') ? $request->date_to : now()->endOfMonth()->format('Y-m-d');
        $request->merge(['date_from' => $dateFrom, 'date_to' => $dateTo]);

        // Get filter-aware base queries for accurate statistics
        $liquidateBaseForStats = $this->getLiquidatesQuery($request, true); // true = skip current status filter
        $disposalBaseForStats = $this->getDisposalsQuery($request, true);   // true = skip current status filter

        // Get paginated results with current status filter applied
        $liquidates = $this->getLiquidatesQuery($request);
        $liquidates = $liquidates->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        $disposals = $this->getDisposalsQuery($request);
        $disposals = $disposals->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        // Calculate statistics that match the filtered view
        $stats = [
            'liquidate' => (clone $liquidateBaseForStats)->count(),
            'disposal' => (clone $disposalBaseForStats)->count(),
        ];

        // Per-tab stats so the status sub-tabs show context-appropriate counts
        $liquidateStats = [
            'all' => (clone $liquidateBaseForStats)->count(),
            'pending' => (clone $liquidateBaseForStats)->where('status', 'pending')->count(),
            'reviewed' => (clone $liquidateBaseForStats)->where('status', 'reviewed')->count(),
            'approved' => (clone $liquidateBaseForStats)->where('status', 'approved')->count(),
        ];

        $disposalStats = [
            'all' => (clone $disposalBaseForStats)->count(),
            'pending' => (clone $disposalBaseForStats)->where('status', 'pending')->count(),
            'reviewed' => (clone $disposalBaseForStats)->where('status', 'reviewed')->count(),
            'approved' => (clone $disposalBaseForStats)->where('status', 'approved')->count(),
        ];

        return inertia('LiquidateDisposal/Index', [
            'liquidates' => LiquidateResource::collection($liquidates),
            'disposals' => DisposalResource::collection($disposals),
            'filters' => $request->only('search', 'per_page', 'page', 'source', 'date_from', 'date_to', 'status'),
            'stats' => $stats,
            'liquidateStats' => $liquidateStats,
            'disposalStats' => $disposalStats,
        ]);
    }

    /**
     * Get liquidations query with filters
     */
    private function getLiquidatesQuery(Request $request, $skipStatusFilter = false)
    {
        $liquidates = Liquidate::query()->with([
            'liquidatedBy:id,name',
            'approvedBy:id,name',
            'reviewedBy:id,name',
            'backOrder',
            'packingList:id,packing_list_number',
            'transfer:id,transferID',
            'order:id,order_number',
        ])->withCount('items')->withSum('items', 'total_cost')->latest('liquidate_id');

        // SECURITY: New granular scoping rules
        $user = auth()->user()->load('warehouse');
        $isRegional = $user->warehouse?->type === 'regional';
        $userRegion = $user->warehouse?->region;
        $userWarehouseName = $user->warehouse?->name;

        if ($isRegional) {
            $liquidates->where(function ($q) use ($userWarehouseName, $userRegion) {
                // 1. Show records from my own warehouse
                $q->where('warehouse', $userWarehouseName)
                // 2. Show records from facilities in my region
                  ->orWhereExists(function ($sub) use ($userRegion) {
                      $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                          ->from('facilities')
                          ->whereColumn('facilities.name', 'liquidates.facility')
                          ->where('facilities.region', $userRegion);
                  });
            });
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $liquidates->where(function ($q) use ($search) {
                $q->where('liquidate_id', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%");
            });
        }

        // Source filter
        if ($request->has('source') && $request->source) {
            $liquidates->where('source', $request->source);
        }

        // Date range filter (always applied; defaults to current month in index())
        $liquidates->whereDate('liquidated_at', '>=', $request->date_from);
        $liquidates->whereDate('liquidated_at', '<=', $request->date_to);

        // Status filter
        if (!$skipStatusFilter && $request->has('status') && $request->status && $request->status !== 'all') {
            $liquidates->where('status', $request->status);
        }

        return $liquidates;
    }

    /**
     * Get disposals query with filters
     */
    private function getDisposalsQuery(Request $request, $skipStatusFilter = false)
    {
        $disposals = Disposal::query()->with([
            'items.product',
            'disposedBy',
            'approvedBy',
            'reviewedBy',
            'backOrder',
            'packingList:id,packing_list_number',
            'transfer:id,transferID',
            'order:id,order_number',
        ])->withCount('items')->withSum('items', 'total_cost')->latest('disposal_id');

        // SECURITY: New granular scoping rules
        $user = auth()->user()->load('warehouse');
        $isRegional = $user->warehouse?->type === 'regional';
        $userRegion = $user->warehouse?->region;
        $userWarehouseName = $user->warehouse?->name;

        if ($isRegional) {
            $disposals->where(function ($q) use ($userWarehouseName, $userRegion) {
                // 1. Show records from my own warehouse
                $q->where('warehouse', $userWarehouseName)
                // 2. Show records from facilities in my region
                  ->orWhereExists(function ($sub) use ($userRegion) {
                      $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                          ->from('facilities')
                          ->whereColumn('facilities.name', 'disposals.facility')
                          ->where('facilities.region', $userRegion);
                  });
            });
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $disposals->where(function ($q) use ($search) {
                $q->where('disposal_id', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%");
            });
        }

        // Source filter
        if ($request->has('source') && $request->source) {
            $disposals->where('source', $request->source);
        }

        // Date range filter (always applied; defaults to current month in index())
        $disposals->whereDate('disposed_at', '>=', $request->date_from);
        $disposals->whereDate('disposed_at', '<=', $request->date_to);

        // Status filter
        if (!$skipStatusFilter && $request->has('status') && $request->status && $request->status !== 'all') {
            $disposals->where('status', $request->status);
        }

        return $disposals;
    }

    /**
     * Show individual liquidation details
     */
    public function showLiquidate($id)
    {
        if (!auth()->user()->hasPermission('wastage-view') && !auth()->user()->hasPermission('liquidation-view')) {
            abort(403, 'You do not have permission to view this liquidation.');
        }

        $liquidate = Liquidate::with([
            'items.product.category',
            'liquidatedBy',
            'approvedBy',
            'reviewedBy',
            'backOrder',
            'packingList:id,packing_list_number',
            'transfer:id,transferID',
            'order:id,order_number',
        ])->findOrFail($id);

        // SECURITY: Ensure user has access to this liquidation
        $user = auth()->user()->load('warehouse');
        $isCentral = $user->warehouse?->type === 'central';
        $isRegional = $user->warehouse?->type === 'regional';
        $userRegion = $user->warehouse?->region;

        if ($isRegional) {
            if ($liquidate->warehouse !== $user->warehouse->name) {
                $facilityInRegion = \App\Models\Facility::where('name', $liquidate->facility)
                    ->where('region', $userRegion)
                    ->exists();
                if (!$facilityInRegion) {
                    abort(403, 'Unauthorized access to this liquidation.');
                }
            }
        } elseif (!$isCentral) {
            abort(403, 'Unauthorized access.');
        }

        $liquidate->setAttribute('source_display', $this->resolveSourceDisplayForLiquidate($liquidate));
        $liquidate->setAttribute('liquidated_by_name', $liquidate->liquidatedBy?->name);

        // Capability icons for UI buttons
        $canReview = false;
        $canApprove = false;
        $canReject = false;

        if ($isCentral) {
            $canReview = $liquidate->warehouse_id == $user->warehouse_id;
            $canApprove = true;
            $canReject = true;
        } elseif ($isRegional) {
            $canReview = true; // scoping already handled region/self access
            $canApprove = false; // Regional users cannot approve
            $canReject = true; 
        }

        $liquidate->setAttribute('can_review', $canReview);
        $liquidate->setAttribute('can_approve', $canApprove);
        $liquidate->setAttribute('can_reject', $canReject);
        $liquidate->setAttribute('can_edit', $canReview); // Rollback to pending is allowed for reviewers

        return inertia('LiquidateDisposal/Liquidate/Show', [
            'liquidate' => $liquidate,
        ]);
    }

    /**
     * Show individual disposal details
     */
    public function showDisposal($id)
    {
        if (!auth()->user()->hasPermission('wastage-view') && !auth()->user()->hasPermission('disposal-view')) {
            abort(403, 'You do not have permission to view this disposal.');
        }

        $disposal = Disposal::with([
            'items.product.category',
            'disposedBy',
            'approvedBy',
            'reviewedBy',
            'backOrder',
            'packingList:id,packing_list_number',
            'transfer:id,transferID',
            'order:id,order_number',
        ])->findOrFail($id);

        // SECURITY: Ensure user has access to this disposal
        $user = auth()->user()->load('warehouse');
        $isCentral = $user->warehouse?->type === 'central';
        $isRegional = $user->warehouse?->type === 'regional';
        $userRegion = $user->warehouse?->region;

        if ($isRegional) {
            if ($disposal->warehouse !== $user->warehouse->name) {
                $facilityInRegion = \App\Models\Facility::where('name', $disposal->facility)
                    ->where('region', $userRegion)
                    ->exists();
                if (!$facilityInRegion) {
                    abort(403, 'Unauthorized access to this disposal.');
                }
            }
        } elseif (!$isCentral) {
            abort(403, 'Unauthorized access.');
        }

        $disposal->setAttribute('source_display', $this->resolveSourceDisplayForDisposal($disposal));
        $disposal->setAttribute('disposed_by_name', $disposal->disposedBy?->name);

        // Capability icons for UI buttons
        $canReview = false;
        $canApprove = false;

        if ($isCentral) {
            $canReview = $disposal->warehouse_id == $user->warehouse_id;
            $canApprove = true;
        } elseif ($isRegional) {
            $canReview = true; // scoping already handled region/self access
            $canApprove = false; // Regional users cannot approve
        }

        $disposal->setAttribute('can_review', $canReview);
        $disposal->setAttribute('can_approve', $canApprove);
        $disposal->setAttribute('can_edit', $canReview); // Rollback to pending is allowed for reviewers

        return inertia('LiquidateDisposal/Disposal/Show', [
            'disposal' => $disposal,
        ]);
    }

    /**
     * Review a liquidation
     */
    public function reviewLiquidate(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('liquidation-review')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to review liquidations.'], 403);
        }

        try {
            $liquidate = Liquidate::findOrFail($id);
            
            // SECURITY: Detailed review rules
            $user = auth()->user()->load('warehouse');
            $isCentral = $user->warehouse?->type === 'central';
            $isRegional = $user->warehouse?->type === 'regional';
            $userRegion = $user->warehouse?->region;
            
            if ($isCentral) {
                // Central can review its own warehouse records
                if ($liquidate->warehouse_id !== $user->warehouse_id) {
                    return response()->json(['message' => 'Central users can only review records belonging to Central Warehouse. Facilities and Regional Warehouses are reviewed by Regional users.'], 403);
                }
            } elseif ($isRegional) {
                // Regional can review self or facilities in their region
                if ($liquidate->warehouse !== $user->warehouse->name) {
                    $facilityInRegion = \App\Models\Facility::where('name', $liquidate->facility)
                        ->where('region', $userRegion)
                        ->exists();
                    if (!$facilityInRegion) {
                        return response()->json(['message' => 'Unauthorized: You can only review records from your own warehouse or facilities in your region.'], 403);
                    }
                }
            }
            if ($liquidate->status !== 'pending') {
                return response()->json([
                    'message' => 'Only pending liquidations can be reviewed'
                ], 422);
            }

            $liquidate->status = 'reviewed';
            $liquidate->reviewed_at = now();
            $liquidate->reviewed_by = Auth::id();
            $liquidate->save();


            return response()->json([
                'message' => 'Liquidation reviewed successfully',
                'liquidate' => $liquidate
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error reviewing liquidation: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a liquidation
     */
    public function approveLiquidate(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('liquidation-approve')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to approve liquidations.'], 403);
        }

        try {
            $liquidate = Liquidate::findOrFail($id);
            
            // SECURITY: Only Central Warehouse can approve
            $user = auth()->user()->load('warehouse');
            $isCentral = $user->warehouse?->type === 'central';
            
            if (!$isCentral) {
                return response()->json(['message' => 'Unauthorized: Approval is restricted to the Central Warehouse.'], 403);
            }

            if ($liquidate->status !== 'reviewed') {
                return response()->json([
                    'message' => 'Only reviewed liquidations can be approved'
                ], 422);
            }

            $liquidate->status = 'approved';
            $liquidate->approved_at = now();
            $liquidate->approved_by = Auth::id();
            $liquidate->save();

            return response()->json([
                'message' => 'Liquidation approved successfully',
                'liquidate' => $liquidate
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error approving liquidation: ' . $th->getMessage()
            ], 500);
        }
    }


    /**
     * Rollback an approved liquidation to pending status
     */
    public function rollbackLiquidate(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('liquidation-edit')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to rollback liquidations.'], 403);
        }

        try {
            $liquidate = Liquidate::findOrFail($id);
            
            // SECURITY: Ensure user has access
            $user = auth()->user()->load('warehouse');
            $isCentral = $user->warehouse?->type === 'central';
            $isRegional = $user->warehouse?->type === 'regional';
            $userRegion = $user->warehouse?->region;

            if ($isRegional) {
                if ($liquidate->warehouse !== $user->warehouse->name) {
                    $facilityInRegion = \App\Models\Facility::where('name', $liquidate->facility)
                        ->where('region', $userRegion)
                        ->exists();
                    if (!$facilityInRegion) {
                        return response()->json(['message' => 'Unauthorized access to this liquidation.'], 403);
                    }
                }
            } elseif (!$isCentral) {
                return response()->json(['message' => 'Unauthorized access.'], 403);
            }
            $liquidate->status = 'pending';
            $liquidate->approved_by = null;
            $liquidate->approved_at = null;
            $liquidate->save();

            return response()->json([
                'message' => 'Liquidation rolled back successfully',
                'liquidate' => $liquidate
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error rolling back liquidation: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Review a disposal
     */
    public function reviewDisposal(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('disposal-review')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to review disposals.'], 403);
        }

        try {
            $disposal = Disposal::findOrFail($id);
            
            // SECURITY: Detailed review rules
            $user = auth()->user()->load('warehouse');
            $isCentral = $user->warehouse?->type === 'central';
            $isRegional = $user->warehouse?->type === 'regional';
            $userRegion = $user->warehouse?->region;
            
            if ($isCentral) {
                // Central can review its own warehouse records
                if ($disposal->warehouse_id !== $user->warehouse_id) {
                    return response()->json(['message' => 'Central users can only review records belonging to Central Warehouse. Facilities and Regional Warehouses are reviewed by Regional users.'], 403);
                }
            } elseif ($isRegional) {
                // Regional can review self or facilities in their region
                if ($disposal->warehouse !== $user->warehouse->name) {
                    $facilityInRegion = \App\Models\Facility::where('name', $disposal->facility)
                        ->where('region', $userRegion)
                        ->exists();
                    if (!$facilityInRegion) {
                        return response()->json(['message' => 'Unauthorized: You can only review records from your own warehouse or facilities in your region.'], 403);
                    }
                }
            }
            
            if ($disposal->status !== 'pending') {
                return response()->json([
                    'message' => 'Only pending disposals can be reviewed'
                ], 422);
            }

            $disposal->status = 'reviewed';
            $disposal->reviewed_at = now();
            $disposal->reviewed_by = Auth::id();
            $disposal->save();


            return response()->json([
                'message' => 'Disposal reviewed successfully',
                'disposal' => $disposal
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error reviewing disposal: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a disposal
     */
    public function approveDisposal(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('disposal-approve')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to approve disposals.'], 403);
        }

        try {
            $disposal = Disposal::findOrFail($id);
            
            // SECURITY: Only Central Warehouse can approve
            $user = auth()->user()->load('warehouse');
            $isCentral = $user->warehouse?->type === 'central';
            
            if (!$isCentral) {
                return response()->json(['message' => 'Unauthorized: Approval is restricted to the Central Warehouse.'], 403);
            }
            
            if ($disposal->status !== 'reviewed') {
                return response()->json([
                    'message' => 'Only reviewed disposals can be approved'
                ], 422);
            }

            $disposal->status = 'approved';
            $disposal->approved_at = now();
            $disposal->approved_by = Auth::id();
            $disposal->save();

            return response()->json([
                'message' => 'Disposal approved successfully',
                'disposal' => $disposal
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error approving disposal: ' . $th->getMessage()
            ], 500);
        }
    }


    /**
     * Rollback an approved disposal to pending status
     */
    public function rollbackDisposal(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('disposal-edit')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to rollback disposals.'], 403);
        }

        try {
            $disposal = Disposal::findOrFail($id);
            
            // SECURITY: Ensure user has access
            $user = auth()->user()->load('warehouse');
            $isCentral = $user->warehouse?->type === 'central';
            $isRegional = $user->warehouse?->type === 'regional';
            $userRegion = $user->warehouse?->region;

            if ($isRegional) {
                if ($disposal->warehouse !== $user->warehouse->name) {
                    $facilityInRegion = \App\Models\Facility::where('name', $disposal->facility)
                        ->where('region', $userRegion)
                        ->exists();
                    if (!$facilityInRegion) {
                        return response()->json(['message' => 'Unauthorized access to this disposal.'], 403);
                    }
                }
            } elseif (!$isCentral) {
                return response()->json(['message' => 'Unauthorized access.'], 403);
            }
            
            if ($disposal->status !== 'approved') {
                return response()->json([
                    'message' => 'Only approved disposals can be rolled back'
                ], 422);
            }

            $disposal->status = 'pending';
            $disposal->approved_by = null;
            $disposal->approved_at = null;
            $disposal->save();

            return response()->json([
                'message' => 'Disposal rolled back successfully',
                'disposal' => $disposal
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error rolling back disposal: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve source display label for a liquidation (e.g. "Packing list: PKL-000001-001").
     */
    private function resolveSourceDisplayForLiquidate(Liquidate $liquidate): string
    {
        $source = $liquidate->source ?? '';
        if ($liquidate->relationLoaded('packingList') && $liquidate->packingList) {
            return 'Packing list: ' . ($liquidate->packingList->packing_list_number ?? $source);
        }
        if ($liquidate->relationLoaded('transfer') && $liquidate->transfer) {
            return 'Transfer: ' . ($liquidate->transfer->transferID ?? $source);
        }
        if ($liquidate->relationLoaded('order') && $liquidate->order) {
            return 'Order: ' . ($liquidate->order->order_number ?? $source);
        }
        return ucfirst(str_replace('_', ' ', $source ?: 'N/A'));
    }

    /**
     * Resolve source display label for a disposal (e.g. "Packing list: PKL-000001-001").
     */
    private function resolveSourceDisplayForDisposal(Disposal $disposal): string
    {
        $source = $disposal->source ?? '';
        if ($disposal->relationLoaded('packingList') && $disposal->packingList) {
            return 'Packing list: ' . ($disposal->packingList->packing_list_number ?? $source);
        }
        if ($disposal->relationLoaded('transfer') && $disposal->transfer) {
            return 'Transfer: ' . ($disposal->transfer->transferID ?? $source);
        }
        if ($disposal->relationLoaded('order') && $disposal->order) {
            return 'Order: ' . ($disposal->order->order_number ?? $source);
        }
        return ucfirst(str_replace('_', ' ', $source ?: 'N/A'));
    }
}
