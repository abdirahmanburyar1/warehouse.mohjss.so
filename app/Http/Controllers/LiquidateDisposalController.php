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

        // Get liquidations
        $liquidates = $this->getLiquidatesQuery($request);
        $liquidates = $liquidates->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        // Get disposals
        $disposals = $this->getDisposalsQuery($request);
        $disposals = $disposals->paginate($request->input('per_page', 25), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        // Get statistics
        $user = auth()->user();
        $isRegional = (bool)$user->warehouse_id;
        $warehouseName = $user->warehouse->name ?? null;

        $liquidateBase = Liquidate::query();
        $disposalBase = Disposal::query();

        if ($isRegional) {
            $liquidateBase->whereHas('items', fn($q) => $q->where('warehouse', $warehouseName));
            $disposalBase->whereHas('items', fn($q) => $q->where('warehouse', $warehouseName));
        }

        $stats = [
            'liquidate' => (clone $liquidateBase)->count(),
            'disposal' => (clone $disposalBase)->count(),
            'all' => (clone $liquidateBase)->count() + (clone $disposalBase)->count(),
            'pending' => (clone $liquidateBase)->where('status', 'pending')->count() + (clone $disposalBase)->where('status', 'pending')->count(),
            'reviewed' => (clone $liquidateBase)->where('status', 'reviewed')->count() + (clone $disposalBase)->where('status', 'reviewed')->count(),
            'approved' => (clone $liquidateBase)->where('status', 'approved')->count() + (clone $disposalBase)->where('status', 'approved')->count(),
            'rejected' => (clone $liquidateBase)->where('status', 'rejected')->count() + (clone $disposalBase)->where('status', 'rejected')->count(),
        ];

        return inertia('LiquidateDisposal/Index', [
            'liquidates' => LiquidateResource::collection($liquidates),
            'disposals' => DisposalResource::collection($disposals),
            'filters' => $request->only('search', 'per_page', 'page', 'source', 'date_from', 'date_to', 'status'),
            'stats' => $stats,
        ]);
    }

    /**
     * Get liquidations query with filters
     */
    private function getLiquidatesQuery(Request $request)
    {
        $liquidates = Liquidate::query()->with([
            'liquidatedBy:id,name',
            'approvedBy:id,name',
            'reviewedBy:id,name',
            'rejectedBy:id,name',
            'backOrder',
            'packingList:id,packing_list_number',
            'transfer:id,transferID',
            'order:id,order_number',
        ])->withCount('items')->withSum('items', 'total_cost')->latest('liquidate_id');

        // SECURITY: If regional user, restrict to their warehouse
        $user = auth()->user();
        if ($user->warehouse_id) {
            $liquidates->whereHas('items', fn($q) => $q->where('warehouse', $user->warehouse->name));
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
        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $liquidates->where('status', $request->status);
        }

        return $liquidates;
    }

    /**
     * Get disposals query with filters
     */
    private function getDisposalsQuery(Request $request)
    {
        $disposals = Disposal::query()->with([
            'items.product',
            'disposedBy',
            'approvedBy',
            'reviewedBy',
            'rejectedBy',
            'backOrder',
            'packingList:id,packing_list_number',
            'transfer:id,transferID',
            'order:id,order_number',
        ])->latest('disposal_id');

        // SECURITY: If regional user, restrict to their warehouse
        $user = auth()->user();
        if ($user->warehouse_id) {
            $disposals->whereHas('items', fn($q) => $q->where('warehouse', $user->warehouse->name));
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
        if ($request->has('status') && $request->status && $request->status !== 'all') {
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
            'rejectedBy',
            'backOrder',
            'packingList:id,packing_list_number',
            'transfer:id,transferID',
            'order:id,order_number',
        ])->findOrFail($id);

        // SECURITY: Ensure user has access to this liquidation
        $user = auth()->user();
        if ($user->warehouse_id) {
            $hasAccess = $liquidate->items()->where('warehouse', $user->warehouse->name)->exists();
            if (!$hasAccess) {
                abort(403, 'Unauthorized access to this liquidation.');
            }
        }

        $liquidate->setAttribute('source_display', $this->resolveSourceDisplayForLiquidate($liquidate));
        $liquidate->setAttribute('liquidated_by_name', $liquidate->liquidatedBy?->name);

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
            'rejectedBy',
            'backOrder',
            'packingList:id,packing_list_number',
            'transfer:id,transferID',
            'order:id,order_number',
        ])->findOrFail($id);

        // SECURITY: Ensure user has access to this disposal
        $user = auth()->user();
        if ($user->warehouse_id) {
            $hasAccess = $disposal->items()->where('warehouse', $user->warehouse->name)->exists();
            if (!$hasAccess) {
                abort(403, 'Unauthorized access to this disposal.');
            }
        }

        $disposal->setAttribute('source_display', $this->resolveSourceDisplayForDisposal($disposal));
        $disposal->setAttribute('disposed_by_name', $disposal->disposedBy?->name);

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
            
            // SECURITY: Ensure user has access
            $user = auth()->user();
            if ($user->warehouse_id) {
                $hasAccess = $liquidate->items()->where('warehouse', $user->warehouse->name)->exists();
                if (!$hasAccess) {
                    return response()->json(['message' => 'Unauthorized access to this liquidation.'], 403);
                }
            }
            $liquidate->status = 'reviewed';
            $liquidate->reviewed_at = now();
            $liquidate->reviewed_by = Auth::id();
            $liquidate->save();

            // Notify users with approve/reject permission (next action)
            $approvers = User::withPermission('liquidation-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $rejectors = User::withPermission('liquidation-reject')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $recipients = $approvers->merge($rejectors)->unique('id');
            foreach ($recipients as $user) {
                $user->notify(new LiquidationActionRequired($liquidate, LiquidationActionRequired::ACTION_READY_FOR_APPROVAL));
            }

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
            
            // SECURITY: Ensure user has access
            $user = auth()->user();
            if ($user->warehouse_id) {
                $hasAccess = $liquidate->items()->where('warehouse', $user->warehouse->name)->exists();
                if (!$hasAccess) {
                    return response()->json(['message' => 'Unauthorized access to this liquidation.'], 403);
                }
            }
            $liquidate->status = 'approved';
            $liquidate->approved_at = now();
            $liquidate->approved_by = Auth::id();
            $liquidate->rejected_at = null;
            $liquidate->rejection_reason = null;
            $liquidate->rejected_by = null;
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
     * Reject a liquidation
     */
    public function rejectLiquidate(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('liquidation-reject')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to reject liquidations.'], 403);
        }

        try {
            $liquidate = Liquidate::findOrFail($id);
            
            // SECURITY: Ensure user has access
            $user = auth()->user();
            if ($user->warehouse_id) {
                $hasAccess = $liquidate->items()->where('warehouse', $user->warehouse->name)->exists();
                if (!$hasAccess) {
                    return response()->json(['message' => 'Unauthorized access to this liquidation.'], 403);
                }
            }
            $liquidate->status = 'rejected';
            $liquidate->rejected_at = now();
            $liquidate->rejected_by = Auth::id();
            $liquidate->rejection_reason = $request->reason;
            $liquidate->save();

            return response()->json([
                'message' => 'Liquidation rejected successfully',
                'liquidate' => $liquidate
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error rejecting liquidation: ' . $th->getMessage()
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
            $user = auth()->user();
            if ($user->warehouse_id) {
                $hasAccess = $liquidate->items()->where('warehouse', $user->warehouse->name)->exists();
                if (!$hasAccess) {
                    return response()->json(['message' => 'Unauthorized access to this liquidation.'], 403);
                }
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
            
            // SECURITY: Ensure user has access
            $user = auth()->user();
            if ($user->warehouse_id) {
                $hasAccess = $disposal->items()->where('warehouse', $user->warehouse->name)->exists();
                if (!$hasAccess) {
                    return response()->json(['message' => 'Unauthorized access to this disposal.'], 403);
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

            // Notify users with approve/reject permission (next action)
            $approvers = User::withPermission('disposal-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $rejectors = User::withPermission('disposal-reject')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get();
            $recipients = $approvers->merge($rejectors)->unique('id');
            foreach ($recipients as $user) {
                $user->notify(new DisposalActionRequired($disposal, DisposalActionRequired::ACTION_READY_FOR_APPROVAL));
            }

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
            
            // SECURITY: Ensure user has access
            $user = auth()->user();
            if ($user->warehouse_id) {
                $hasAccess = $disposal->items()->where('warehouse', $user->warehouse->name)->exists();
                if (!$hasAccess) {
                    return response()->json(['message' => 'Unauthorized access to this disposal.'], 403);
                }
            }
            
            if (!in_array($disposal->status, ['reviewed', 'rejected'])) {
                return response()->json([
                    'message' => 'Only reviewed or rejected disposals can be approved'
                ], 422);
            }

            $disposal->status = 'approved';
            $disposal->approved_at = now();
            $disposal->approved_by = Auth::id();
            $disposal->rejected_at = null;
            $disposal->rejection_reason = null;
            $disposal->rejected_by = null;
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
     * Reject a disposal
     */
    public function rejectDisposal(Request $request, $id)
    {
        if (!auth()->user()->hasPermission('disposal-reject')) {
            return response()->json(['message' => 'Unauthorized: You do not have permission to reject disposals.'], 403);
        }

        try {
            $disposal = Disposal::findOrFail($id);
            
            // SECURITY: Ensure user has access
            $user = auth()->user();
            if ($user->warehouse_id) {
                $hasAccess = $disposal->items()->where('warehouse', $user->warehouse->name)->exists();
                if (!$hasAccess) {
                    return response()->json(['message' => 'Unauthorized access to this disposal.'], 403);
                }
            }
            
            if ($disposal->status !== 'reviewed') {
                return response()->json([
                    'message' => 'Only reviewed disposals can be rejected'
                ], 422);
            }

            $disposal->status = 'rejected';
            $disposal->rejected_at = now();
            $disposal->rejected_by = Auth::id();
            $disposal->rejection_reason = $request->reason;
            $disposal->save();

            return response()->json([
                'message' => 'Disposal rejected successfully',
                'disposal' => $disposal
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error rejecting disposal: ' . $th->getMessage()
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
            $user = auth()->user();
            if ($user->warehouse_id) {
                $hasAccess = $disposal->items()->where('warehouse', $user->warehouse->name)->exists();
                if (!$hasAccess) {
                    return response()->json(['message' => 'Unauthorized access to this disposal.'], 403);
                }
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
