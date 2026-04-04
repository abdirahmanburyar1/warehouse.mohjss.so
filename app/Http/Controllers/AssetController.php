<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetItem;
use App\Models\SubLocation;
use App\Models\AssetLocation;
use App\Models\Region;
use App\Models\District;
use App\Models\User;
use App\Models\Assignee;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\AssetHistory;
use App\Models\FundSource;
use App\Models\Facility;
use App\Models\AssetApprovalItem;
use App\Http\Resources\AssetResource;
use App\Http\Resources\AssetItemResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AssetsImport;
use App\Notifications\AssetActionRequired;
use Inertia\Inertia;
use Illuminate\Support\Carbon;

class AssetController extends Controller
{
    private function getFilteredAssets(Request $request, $query, $requireFilters = true)
    {
        // 1. Initial filter check - only count EXPLICIT user selections
        $userFilters = [
            'search', 'region_id', 'location_id', 'district_id', 
            'fund_source_id', 'status', 'category_id', 'type_id', 
            'assignee_id', 'acquisition_from', 'acquisition_to', 
            'created_from', 'asset_id', 'created_to', 'selectedAsset'
        ];

        $hasExplicitFilters = false;
        foreach ($userFilters as $filter) {
            if ($request->filled($filter)) {
                $hasExplicitFilters = true;
                break;
            }
        }

        $user = auth()->user();
        $isRegional = !$user->isAdmin() && $user->warehouse?->region;

        if ($isRegional) {
            $regionName = trim($user->warehouse->region);
            $region = Region::where('name', $regionName)->first();
            if ($region) {
                // If they specifically tried to filter by another region, the explicit check would be true,
                // but we must still force their region scope.
                $query->whereHas('asset', function($q) use ($region) {
                    $q->where('region_id', $region->id);
                });
            }
        }

        if ($requireFilters && !$hasExplicitFilters) {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        if ($request->filled('selectedAsset')) {
            $query->whereHas('asset', function($q) use ($request) {
                $q->where('asset_number', $request->selectedAsset);
            });
        }

        // Apply filters
        if($request->filled('search')){
            $query->where(function($q) use ($request) {
                $q->whereLike('asset_tag', '%'.$request->search.'%')   
                    ->orWhereLike('asset_name', '%'.$request->search.'%')
                    ->orWhereLike('serial_number', '%'.$request->search.'%')
                    ->orWhereHas('asset.fundSource', function($innerQ) use ($request){
                        $innerQ->where('name', 'like', '%'.$request->search.'%');
                    });
                });
        }

        if($request->filled('region_id')){
            $query->whereHas('asset', function($q) use ($request) {
                $q->where('region_id', $request->region_id);
            });
        }

        if($request->filled('location_id')){
            $query->whereHas('asset', function($q) use ($request) {
                $q->where('facility_id', $request->location_id);
            });
        }

        if($request->filled('district_id')){
            $query->whereHas('asset', function($q) use ($request) {
                $q->where('district_id', $request->district_id);
            });
        }

        if($request->filled('fund_source_id')){
            $query->whereHas('asset', function($q) use ($request) {
                $q->where('fund_source_id', $request->fund_source_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('asset_category_id', $request->category_id);
        }

        if ($request->filled('type_id')) {
            $query->where('asset_type_id', $request->type_id);
        }

        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->input('assignee_id'));
        }

        if ($request->filled('acquisition_from') || $request->filled('acquisition_to')) {
            $from = $request->input('acquisition_from');
            $to = $request->input('acquisition_to');
            if ($from && $to) {
                $query->whereHas('asset', function($q) use ($from, $to) {
                    $q->whereBetween('acquisition_date', [$from, $to]);
                });
            } elseif ($from) {
                $query->whereHas('asset', function($q) use ($from) {
                    $q->whereDate('acquisition_date', '>=', $from);
                });
            } elseif ($to) {
                $query->whereHas('asset', function($q) use ($to) {
                    $q->whereDate('acquisition_date', '<=', $to);
                });
            }
        }

        if ($request->filled('created_from') || $request->filled('created_to')) {
            $from = $request->input('created_from');
            $to = $request->input('created_to');
            if ($from && $to) {
                $query->whereBetween('created_at', [$from, $to]);
            } elseif ($from) {
                $query->whereDate('created_at', '>=', $from);
            } elseif ($to) {
                $query->whereDate('created_at', '<=', $to);
            }
        }

        return $query;
    }

    private function getCommonData()
    {
        $verifiedCount = AssetItem::count();
        $functioningCount = AssetItem::where('status', 'functioning')->count();
        $pendingCount = AssetItem::where('status', 'pending_approval')->count();
        $maintenanceCount = AssetItem::whereIn('status', ['maintenance', 'not_functioning'])->count();

        $stagingCount = Asset::whereNotNull('submitted_at')
            ->whereNull('approved_at')
            ->whereNull('rejected_at')
            ->count();

        $user = auth()->user();
        $isRegional = !$user->isAdmin() && $user->warehouse?->region;
        $regionName = $isRegional ? trim($user->warehouse->region) : null;

        $facilitiesQuery = Facility::orderBy('name');
        if ($regionName) {
            $facilitiesQuery->where('region', $regionName);
        }
        $facilities = $facilitiesQuery->get(['id', 'name', 'district', 'region']);

        $districtsQuery = District::orderBy('name');
        if ($regionName) {
            $districtsQuery->where('region', $regionName);
        }
        $districts = $districtsQuery->get();

        $regionsQuery = Region::query();
        if ($regionName) {
            $regionsQuery->where('name', $regionName);
        }
        $regions = $regionsQuery->get();

        $categories = AssetCategory::select('id', 'name')->get();
        $types = AssetType::select('id', 'name', 'asset_category_id')->get();
        $assignees = Assignee::select('id', 'name')->get();

        return [
            'facilities' => $facilities,
            'districts' => $districts,
            'assetsCount' => $verifiedCount,
            'functioningCount' => $functioningCount,
            'pendingCount' => $pendingCount,
            'maintenanceCount' => $maintenanceCount,
            'stagingCount' => $stagingCount,
            'regions' => $regions,
            'fundSources' => FundSource::get(['id', 'name']),
            'categories' => $categories,
            'types' => $types,
            'assignees' => $assignees,
        ];
    }

    public function index(Request $request)
    {
        $query = AssetItem::query();
        $assetItems = $this->getFilteredAssets($request, $query, true);
        $assetItems->orderBy('created_at', 'desc');

        $assetItems = $assetItems->with([
            'asset:id,asset_number,acquisition_date,fund_source_id,region_id,district_id,facility_id,sub_location_id,submitted_at,reviewed_at,approved_at,rejected_at',
            'asset.fundSource:id,name',
            'asset.region:id,name',
            'asset.district:id,name',
            'asset.facility:id,name',
            'asset.subLocation:id,name',
            'category:id,name',
            'type:id,name',
            'assignee:id,name',
            'fundSource:id,name'
        ])
            ->paginate($request->input('per_page', 10), ['*'], 'page', $request->input('page', 1))
            ->withQueryString();

        $data = $this->getCommonData();

        $assetItems->setPath(url()->current());

        return Inertia::render('Assets/Index', array_merge($data, [
            'assets' => AssetItemResource::collection($assetItems),
            'filters' => $request->only('page', 'per_page', 'search', 'region_id', 'district_id', 'location_id', 'sub_location_id', 'fund_source_id', 'category_id', 'type_id', 'assignee_id', 'acquisition_from', 'acquisition_to', 'created_from', 'created_to', 'status'),
        ]));
    }

    public function create()
    {
        // Check if user has permission to create assets
        if (!auth()->user()->hasPermission('asset-create') && !auth()->user()->isAdmin()) {
            return redirect()->route('assets.index')->with('error', 'You do not have permission to create assets.');
        }

        $user = auth()->user();
        $isRegional = !$user->isAdmin() && $user->warehouse?->region;
        $regionName = $isRegional ? trim($user->warehouse->region) : null;

        $facilitiesQuery = Facility::orderBy('name');
        if ($isRegional) {
            $facilitiesQuery->where('region', $regionName);
        }
        $facilities = $facilitiesQuery->get(['id', 'name', 'district', 'region']);

        $districtsQuery = District::orderBy('name');
        if ($isRegional) {
            $districtsQuery->where('region', $regionName);
        }
        $districts = $districtsQuery->get();

        $regionsQuery = Region::query();
        if ($isRegional) {
            $regionsQuery->where('name', $regionName);
        }
        $regions = $regionsQuery->get();
        
        $locations = $facilities;
        $categories = AssetCategory::all();
        $fundSources = FundSource::get();
        $types = AssetType::all();
        $users = User::select('id','name','email')->get();
        $assignees = Assignee::select('id','name')->orderBy('name')->get();
        
        return Inertia::render('Assets/Create', [
            'locations' => $locations,
            'categories' => $categories,
            'fundSources' => $fundSources,
            'regions' => $regions,
            'districts' => $districts,
            'types' => $types,
            'users' => $users,
            'assignees' => $assignees,
            'facilities' => $facilities,
        ]);
    }

    public function store(Request $request)
    {
        // Check if user has permission to create assets
        if (!auth()->user()->hasPermission('asset-create') && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create assets.'
            ], 403);
        }

        try {
            return DB::transaction(function() use ($request){
                // Validate the main asset data
                $validatedAsset = $request->validate([
                    'asset_number' => 'nullable|string|unique:assets,asset_number',
                    'acquisition_date' => 'required|date',
                    'fund_source_id' => 'required|exists:fund_sources,id',
                    'region_id' => 'required|exists:regions,id',
                    'district_id' => 'required|exists:districts,id',
                    'facility_id' => 'required|exists:facilities,id',
                ]);

                // Regional validation
                $user = auth()->user();
                if (!$user->isAdmin() && $user->warehouse && $user->warehouse->region) {
                    $region = Region::where('name', trim($user->warehouse->region))->first();
                    if ($region && (int)$validatedAsset['region_id'] !== $region->id) {
                        throw new \Exception('Unauthorized: Region must match your warehouse region.');
                    }
                    
                    // Also check facility region
                    $facility = Facility::find($validatedAsset['facility_id']);
                    if ($facility && trim($facility->region) !== trim($user->warehouse->region)) {
                        throw new \Exception('Unauthorized: Facility must be in your region.');
                    }
                }

                // Validate asset items array
                $request->validate([
                    'asset_items' => 'required|array|min:1',
                    'asset_items.*.asset_tag' => 'required|string|max:255',
                    'asset_items.*.asset_name' => 'required|string|max:255',
                    'asset_items.*.serial_number' => 'required|string|max:255',
                    'asset_items.*.asset_category_id' => 'required|exists:asset_categories,id',
                    'asset_items.*.asset_type_id' => 'required|exists:asset_types,id',
                    'asset_items.*.assignee_id' => 'nullable|exists:assignees,id',
                    'asset_items.*.status' => 'required|string|in:functioning,not_functioning,maintenance,disposed',
                    'asset_items.*.original_value' => 'required|numeric|min:0',
                ]);

                // Get asset items data directly
                $assetItemsData = $request->asset_items;
                
                // Generate asset number if not provided
                if (empty($validatedAsset['asset_number'])) {
                    $validatedAsset['asset_number'] = $this->generateAssetNumber();
                }

                // Set default status for the asset
                $validatedAsset['status'] = 'pending_approval';
                $validatedAsset['submitted_by'] = auth()->id();
                $validatedAsset['submitted_at'] = now();
                
                // Handle nullable sub_location_id
                if (empty($validatedAsset['sub_location_id'])) {
                    $validatedAsset['sub_location_id'] = null;
                }

        // Handle nullable facility_id
        // if (empty($validatedAsset['facility_id'])) {
        //     $validatedAsset['facility_id'] = null;
        // }

                // Create the main asset record
                $asset = Asset::create($validatedAsset);

                // Create asset items in the staging table
                $assetItems = [];
                foreach ($assetItemsData as $itemData) {
                    $assetItem = \App\Models\AssetApprovalItem::create([
                        'asset_id' => $asset->id,
                        'asset_tag' => $itemData['asset_tag'],
                        'asset_name' => $itemData['asset_name'],
                        'serial_number' => $itemData['serial_number'],
                        'asset_category_id' => $itemData['asset_category_id'],
                        'asset_type_id' => $itemData['asset_type_id'],
                        'assignee_id' => $itemData['assignee_id'],
                        'status' => $itemData['status'] ?? 'functioning',
                        'original_value' => $itemData['original_value'],
                        'fund_source_id' => $validatedAsset['fund_source_id'],
                        'acquisition_date' => $validatedAsset['acquisition_date'],
                    ]);

                    $assetItems[] = $assetItem;
                }

                // Create asset history record for creation
                $asset->createHistory([
                    'action' => 'asset_created',
                    'action_type' => 'creation',
                    'notes' => 'Asset created with ' . count($assetItems) . ' items',
                    'performed_by' => auth()->id(),
                    'performed_at' => now(),
                ]);


                // Notify users with asset-review permission (workflow: next action is review)
                User::withPermission('asset-review')
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->where('id', '!=', auth()->id())
                    ->get()
                    ->each(fn ($u) => $u->notify(new AssetActionRequired($asset, AssetActionRequired::ACTION_NEEDS_REVIEW)));

                return response()->json([
                    'success' => true,
                    'message' => 'Asset created successfully with ' . count($assetItems) . ' items. Pending approval.',
                    'asset' => $asset->load('assetItems'),
                    'redirect_url' => route('assets.index')
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create asset: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Generate a unique asset number
     */
    private function generateAssetNumber()
    {
        $prefix = 'AST';
        $year = date('Y');
        $month = date('m');
        
        // Get the last asset number for this month
        $lastAsset = Asset::where('asset_number', 'like', $prefix . $year . $month . '%')
            ->orderBy('asset_number', 'desc')
            ->first();
        
        if ($lastAsset) {
            // Extract the sequence number and increment
            $lastSequence = (int) substr($lastAsset->asset_number, -4);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }
        
        return $prefix . $year . $month . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    public function edit(Asset $asset)
    {
        // Load the asset with its actual relationships, filtered by organization through asset items
        $assetQuery = Asset::with(['region', 'district', 'fundSource', 'facility']);
            
        $user = auth()->user();
        if (!$user->isAdmin() && $user->warehouse && $user->warehouse->region) {
            $region = Region::where('name', trim($user->warehouse->region))->first();
            if ($region) {
                $assetQuery->where('region_id', $region->id);
            }
        }

        if ($user && !empty($user->organization)) {
            $assetQuery->whereHas('assetItems', function($query) use ($user) {
                $query->where('organization', $user->organization);
            });
        }
        
        $asset = $assetQuery->findOrFail($asset->id);
            
        // Get the first asset item for additional details
        $assetItem = $asset->assetItems()->with(['category', 'type', 'assignee'])->first();
        
        $isRegional = !$user->isAdmin() && $user->warehouse?->region;
        $regionName = $isRegional ? trim($user->warehouse->region) : null;

        $facilitiesQuery = Facility::orderBy('name');
        if ($isRegional) {
            $facilitiesQuery->where('region', $regionName);
        }
        $locations = $facilitiesQuery->get(['id', 'name', 'district', 'region']);

        $districtsQuery = District::orderBy('name');
        if ($isRegional) {
            $districtsQuery->where('region', $regionName);
        }
        $districts = $districtsQuery->get();

        $regionsQuery = Region::query();
        if ($isRegional) {
            $regionsQuery->where('name', $regionName);
        }
        $regions = $regionsQuery->get();

        $categories = AssetCategory::orderBy('name')->get();
        $fundSources = FundSource::orderBy('name')->get();
        $types = AssetType::orderBy('name')->get();
        $assignees = Assignee::select('id','name')->orderBy('name')->get();
        $facilities = $locations;
        
        return Inertia::render('Assets/Edit', [
            'asset' => $asset,
            'assetItem' => $assetItem,
            'locations' => $locations,
            'categories' => $categories,
            'fundSources' => $fundSources,
            'regions' => $regions,
            'districts' => $districts,
            'types' => $types,
            'assignees' => $assignees,
            'facilities' => $facilities,
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        try {
            // Validate asset-level fields
            $assetValidated = $request->validate([
                'region_id' => 'required|exists:regions,id',
                'asset_location_id' => 'required|exists:asset_locations,id',
                'sub_location_id' => 'required|exists:sub_locations,id',
                'facility_id' => 'nullable|exists:facilities,id',
                'fund_source_id' => 'required|exists:fund_sources,id',
                'acquisition_date' => 'required|date',
            ]);
            if (empty($assetValidated['facility_id'])) {
                $assetValidated['facility_id'] = null;
            }

            // Update the asset
            $user = auth()->user();
            if (!$user->isAdmin() && $user->warehouse && $user->warehouse->region) {
                if ($asset->region?->name !== trim($user->warehouse->region)) {
                    return response()->json('Unauthorized: You can only update assets in your region.', 403);
                }
                
                // If they are trying to change the region
                $newRegion = Region::find($assetValidated['region_id']);
                if ($newRegion && $newRegion->name !== trim($user->warehouse->region)) {
                    return response()->json('Unauthorized: You cannot move an asset out of your region.', 403);
                }
            }

            $asset->update($assetValidated);

            // Validate and update asset item fields if provided
            if ($request->has('asset_item_data')) {
                $assetItemValidated = $request->validate([
                    'asset_item_data.asset_tag' => 'required|string|max:255',
                    'asset_item_data.asset_category_id' => 'required|exists:asset_categories,id',
                    'asset_item_data.asset_type_id' => 'required|exists:asset_types,id',
                    'asset_item_data.asset_name' => 'required|string|max:255',
                    'asset_item_data.serial_number' => 'required|string|max:255',
                    'asset_item_data.original_value' => 'required|numeric|min:0',
                    'asset_item_data.status' => 'required|string|in:functioning,not_functioning,pending_approval,maintenance,disposed',
                    'asset_item_data.assignee_id' => 'nullable|exists:assignees,id',
                ]);

                // Update the first asset item
                $assetItem = $asset->assetItems()->first();
                if ($assetItem) {
                    $assetItem->update($assetItemValidated['asset_item_data']);
                }
            }

            return response()->json('Updated', 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();
        return response()->json('Deleted', 200);
    }

    /**
     * Toggle asset item status between functioning and not_functioning.
     */
    public function toggleStatus(Request $request, AssetItem $assetItem)
    {
        $request->validate([
            'status' => 'required|string|in:functioning,not_functioning',
        ]);

        $assetItem->update(['status' => $request->status]);

        return response()->json([
            'status' => $assetItem->status,
            'message' => 'Status updated successfully',
        ], 200);
    }

    /**
     * Display the asset locations index page.
     */
    public function locationIndex()
    {
        $locations = Facility::all();
        return Inertia::render('Assets/Locations/Index', [
            'locations' => $locations
        ]);
    }
    
    /**
     * Display the asset sub-locations index page.
     */
    public function subLocationIndex()
    {
        $subLocations = SubLocation::with('location')->get();
        $locations = Facility::all();
        return Inertia::render('Assets/SubLocations/Index', [
            'subLocations' => $subLocations,
            'locations' => $locations
        ]);
    }

    public function getSubLocations($locationId)
    {
        $subLocations = SubLocation::where('facility_id', $locationId)->get();
        return response()->json($subLocations);
    }

    /**
     * Store a new sub-location.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeSubLocation(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'asset_location_id' => 'required|exists:facilities,id'
            ]);
            
            $subLocation = SubLocation::create([
                'name' => $request->name,
                'facility_id' => $request->asset_location_id
            ]);
            
            return response()->json($subLocation, 201);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Store a new asset category.
     */
    public function storeCategory(Request $request){
        try {
            $request->validate([
                'name' => 'required',
            ]);
            
            $category = AssetCategory::create([
                'name' => $request->name
            ]);
            
            return response()->json($category, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Store a new asset location.
     */
    public function storeAssetLocation(Request $request){
        try {
            $request->validate([
                'name' => 'required',
            ]);
            $location = AssetLocation::create([
                'name' => $request->name
            ]);
            return response()->json($location, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Store a new fund source.
     */
    public function storeFundSource(Request $request){
        try {
            $request->validate([
                'name' => 'required',
            ]);
            $fundSource = FundSource::create([
                'name' => $request->name
            ]);
            return response()->json($fundSource, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Store a new region.
     */
    public function storeRegion(Request $request){
        // Allow creating regions even without organization
        // This allows admins to create regions
        
        try {
            $request->validate([
                'name' => 'required|unique:regions',
            ],[
                'name.unique' => $request->name . " already exists",
            ]);
            
            $region = Region::create([
                'name' => $request->name
            ]);
            
            return response()->json($region->name, 200);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Display the approvals index page
     */
    public function approvalsIndex(Request $request)
    {
        $user = auth()->user();
        $assets = Asset::whereNotNull('submitted_at')
            ->whereHas('assetApprovalItems');
            
        if ($user && !empty($user->organization)) {
            $assets->where('organization', $user->organization);
        }
        
        if (!$user->isAdmin() && $user->warehouse && $user->warehouse->region) {
            $region = Region::where('name', trim($user->warehouse->region))->first();
            if ($region) {
                $assets->where('region_id', $region->id);
            }
        }

        $assets = $assets
                      ->with(['region', 'facility', 'subLocation'])
                      ->get(['id', 'asset_number', 'acquisition_date', 'region_id', 'facility_id', 'sub_location_id'])
                      ->map(function($asset) {
                          return [
                              'id' => $asset->id,
                              'asset_number' => $asset->asset_number,
                              'acquisition_date' => $asset->acquisition_date,
                              'region_name' => $asset->region?->name,
                              'location_name' => $asset->facility?->name,
                              'sub_location_name' => $asset->subLocation?->name,
                          ];
                      })
                      ->toArray();
        
        $assetItems = $this->getFilteredAssets($request, \App\Models\AssetApprovalItem::query(), true);
        
        $data = $this->getCommonData();

        $selectedAssetRecord = null;
        if ($request->selectedAsset) {
            $selectedAssetRecord = Asset::where('asset_number', $request->selectedAsset)
                ->with(['reviewedBy', 'approvedBy', 'fundSource', 'region', 'facility'])
                ->first();
        }

        return Inertia::render('Assets/PendingApprovals', array_merge($data, [
            'assets' => $assetItems->with([
                'asset:id,asset_number,acquisition_date,fund_source_id,region_id,district_id,facility_id,sub_location_id',
                'asset.fundSource:id,name',
                'asset.region:id,name',
                'asset.district:id,name',
                'asset.facility:id,name',
                'asset.subLocation:id,name',
                'category:id,name',
                'type:id,name',
                'assignee:id,name',
                'fundSource:id,name'
            ])->paginate($request->input('per_page', 10), ['*'], 'page', $request->input('page', 1))->withQueryString(),
            'unapprovedAssets' => $assets,
            'assetItem' => $selectedAssetRecord,
            'filters' => array_merge(
                $request->only('page', 'per_page', 'search', 'region_id', 'district_id', 'location_id', 'sub_location_id', 'fund_source_id', 'category_id', 'type_id', 'assignee_id', 'acquisition_from', 'acquisition_to', 'created_from', 'created_to', 'status', 'selectedAsset'),
                ['asset_id' => $request->selectedAsset]
            ),
        ]));
    }

    /**
     * Approve an asset (simple approval for new structure)
     */
    public function approve(Asset $asset)
    {
        $user = auth()->user();
        if (!$user->hasPermission('asset-approve') && !$user->hasPermission('asset-manage') && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to approve assets.'], 403);
        }
        try {
            return DB::transaction(function () use ($asset) {
                // Check if user has access to this asset through organization
                if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                    $hasAccess = $asset->organization === auth()->user()->organization;
                    if (!$hasAccess) {
                        return response()->json([
                            'success' => false,
                            'message' => 'You do not have access to this asset'
                        ], 403);
                    }
                }

                // Check if asset is in reviewed status (has reviewed_at but no approved_at)
                if (!$asset->reviewed_at) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Asset must be reviewed before approval'
                    ], 400);
                }

                if ($asset->approved_at) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Asset is already approved'
                    ], 400);
                }

                // 1. Handle Staging Items (AssetApprovalItem)
                $stagingItems = AssetApprovalItem::where('asset_id', $asset->id)->get();
                foreach ($stagingItems as $item) {
                    AssetItem::create([
                        'asset_id' => $item->asset_id,
                        'asset_tag' => $item->asset_tag,
                        'asset_name' => $item->asset_name,
                        'serial_number' => $item->serial_number,
                        'asset_category_id' => $item->asset_category_id,
                        'asset_type_id' => $item->asset_type_id,
                        'assignee_id' => $item->assignee_id,
                        'fund_source_id' => $item->fund_source_id,
                        'acquisition_date' => $item->acquisition_date,
                        'status' => $item->status === 'pending_approval' ? 'functioning' : $item->status,
                        'original_value' => $item->original_value,
                    ]);
                }
                
                // Cleanup staging
                AssetApprovalItem::where('asset_id', $asset->id)->delete();

                // 2. Handle Existing Main Items (if any)
                $asset->assetItems()->where('status', 'pending_approval')->update([
                    'status' => 'functioning'
                ]);

                // 3. Finalize Asset status
                $asset->update([
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ]);

                // Create history records
                $asset->createHistory([
                    'action' => 'asset_approved',
                    'action_type' => 'approval',
                    'notes' => 'Asset approved and released from staging by ' . auth()->user()->name,
                    'performed_by' => auth()->id(),
                    'performed_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Asset finalized and approved successfully!',
                    'asset' => $asset->fresh(['approvedBy', 'assetItems'])
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve asset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject an asset (simple rejection for new structure)
     */
    public function reject(Request $request, Asset $asset)
    {
        $user = auth()->user();
        if (!$user->hasPermission('asset-approve') && !$user->hasPermission('asset-reject') && !$user->hasPermission('asset-manage') && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to reject assets.'], 403);
        }
        try {
            // Check if user has access to this asset through organization
            if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                $hasAccess = $asset->organization === auth()->user()->organization;
                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this asset'
                    ], 403);
                }
            }

            // Check if asset is in reviewed status (has reviewed_at but no approved_at)
            if (!$asset->reviewed_at || $asset->approved_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset must be reviewed before rejection'
                ], 400);
            }

            $request->validate([
                'rejection_reason' => 'required|string|max:500'
            ]);

            $asset->update([
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Delete associated staging items (AssetApprovalItem)
            AssetApprovalItem::where('asset_id', $asset->id)->delete();

            // Create history records
            $asset->createHistory([
                'action' => 'asset_rejected',
                'action_type' => 'rejection',
                'notes' => 'Asset rejected: ' . $request->rejection_reason,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
            ]);

            foreach ($asset->assetItems as $item) {
                $item->createHistory([
                    'action' => 'item_rejected',
                    'action_type' => 'rejection',
                    'notes' => 'Asset item rejected',
                    'performed_by' => auth()->id(),
                    'performed_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asset rejected successfully',
                'asset' => $asset->fresh(['rejectedBy', 'assetItems'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject asset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Review an asset (mark as reviewed)
     */
    public function review(Asset $asset)
    {
        $user = auth()->user();
        if (!$user->hasPermission('asset-review') && !$user->hasPermission('asset-manage') && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to review assets.'], 403);
        }
        try {
            // Check if user has access to this asset through organization
            if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                $hasAccess = $asset->organization === auth()->user()->organization;
                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this asset'
                    ], 403);
                }
            }

            // Check if asset is submitted for approval (has submitted_at but no reviewed_at)
            if (!$asset->submitted_at || $asset->reviewed_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset must be submitted for approval before review'
                ], 400);
            }

            $asset->update([
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            // Create history record
            $asset->createHistory([
                'action' => 'asset_reviewed',
                'action_type' => 'review',
                'notes' => 'Asset reviewed by ' . auth()->user()->name,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
            ]);

            // Notify users with asset-approve or asset-reject (workflow: next action is approve/reject)
            $recipients = User::withPermission('asset-approve')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', $user->id)
                ->get()
                ->merge(
                    User::withPermission('asset-reject')
                        ->where('is_active', true)
                        ->whereNotNull('email')
                        ->where('id', '!=', $user->id)
                        ->get()
                )
                ->unique('id');
            $recipients->each(fn ($u) => $u->notify(new AssetActionRequired($asset->fresh(), AssetActionRequired::ACTION_READY_FOR_APPROVAL)));

            return response()->json([
                'success' => true,
                'message' => 'Asset reviewed successfully',
                'asset' => $asset->fresh(['reviewedBy'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to review asset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a rejected asset back to pending approval
     */
    public function restore(Asset $asset)
    {
        $user = auth()->user();
        if (!$user->hasPermission('asset-approve') && !$user->hasPermission('asset-manage') && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to restore assets.'], 403);
        }
        try {
            // Check if asset is rejected (has rejected_at)
            if (!$asset->rejected_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset is not rejected'
                ], 400);
            }

            $asset->update([
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            // Create history record
            $asset->createHistory([
                'action' => 'asset_restored',
                'action_type' => 'restore',
                'notes' => 'Asset restored by ' . auth()->user()->name,
                'performed_by' => auth()->id(),
                'performed_at' => now(),
            ]);

            // Notify reviewers that asset is back in workflow and needs review
            User::withPermission('asset-review')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('id', '!=', auth()->id())
                ->get()
                ->each(fn ($u) => $u->notify(new AssetActionRequired($asset->fresh(), AssetActionRequired::ACTION_NEEDS_REVIEW)));

            return response()->json([
                'success' => true,
                'message' => 'Asset restored successfully',
                'asset' => $asset->fresh(['assetItems'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore asset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk approve multiple assets
     */
    public function bulkApprove(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasPermission('asset-approve') && !$user->hasPermission('asset-manage') && !$user->isAdmin()) {
            return back()->withErrors(['error' => 'You do not have permission to approve assets.']);
        }
        try {
            $request->validate([
                'asset_ids' => 'required|array',
                'asset_ids.*' => 'exists:assets,id'
            ]);

            $approvedCount = 0;
            foreach ($request->asset_ids as $assetId) {
                $asset = Asset::where('organization', auth()->user()->organization)->find($assetId);
                if ($asset && $asset->status === 'pending_approval') {
                    $asset->update([
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);

                    $asset->assetItems()->update([
                        'status' => 'functioning'
                    ]);

                    $approvedCount++;
                }
            }

            return back()->with('success', "{$approvedCount} assets approved successfully");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to bulk approve assets: ' . $e->getMessage()]);
        }
    }

    /**
     * Store a new assignee.
     */
    public function storeAssignee(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:50',
                'department' => 'nullable|string|max:100',
            ]);

            $assignee = Assignee::create($request->all());
            return response()->json($assignee, 201);
        } catch (\Throwable $th) {
            return response()->json($th->getMessage(), 500);
        }
    }

    /**
     * Transfer an asset to a new assignee.
     */
    public function transferAsset(Request $request, Asset $asset)
    {
        try {
            // Check if asset exists and is accessible
            if (!$asset) {
                return response()->json(['error' => 'Asset not found'], 404);
            }

            $request->validate([
                'assignee_id' => 'required|exists:assignees,id',
                'transfer_date' => 'required|date',
                'assignment_notes' => 'nullable|string',
                'update_asset_location' => 'nullable|boolean',
                'region_id' => 'nullable|exists:regions,id',
                'district_id' => 'nullable|exists:districts,id',
                'facility_id' => 'nullable|exists:facilities,id',
            ]);

            // For asset transfers, we typically don't want to change the asset's location
            // as it affects all asset items under that asset. Instead, we only update
            // the specific asset item's assignee and status.
            
            // Only update asset location if explicitly requested
            if ($request->has('update_asset_location') && $request->update_asset_location) {                
                $asset->update([
                    'region_id' => $request->region_id,
                    'district_id' => $request->district_id,
                    'facility_id' => $request->facility_id,
                ]);
            }


            // Update only the specific asset item that was transferred
            // Since we're transferring a specific asset item, we need to find it
            $assetItems = $asset->assetItems()->get();
            
            $assetItem = $assetItems->first();
            
            if ($assetItem) {
                $assetItem->update([
                    'assignee_id' => $request->assignee_id,
                    'status' => 'functioning',
                ]);
            }


            // Create transfer history record for the specific asset item
            if ($assetItem) {
                $assetItem->createHistory([
                    'action' => 'asset_transferred',
                    'action_type' => 'transfer',
                    'notes' => 'Asset transferred to ' . $request->assignee_name . ' on ' . $request->transfer_date . 
                               ($request->assignment_notes ? ' - Notes: ' . $request->assignment_notes : ''),
                    'performed_by' => auth()->id(),
                    'performed_at' => now(),
                    'assignee_id' => $request->assignee_id,
                ]);
            }

            return response()->json([
                'message' => 'Asset transferred successfully',
                'asset' => $asset->fresh(['assetItems.assignee', 'region', 'facility', 'subLocation'])
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Transfer failed: ' . $th->getMessage()], 500);
        }
    }


    /**
     * Show the details of an asset.
     */
    public function show(Request $request, $asset)
    {
        try {
            // Get the asset with its relationships, filtered by organization through asset items
            $assetWithRelations = Asset::with([
                'documents',
                'assetItems.assetHistory.performer',
                'region',
                'district',
                'facility',
                'subLocation',
                'fundSource',
                'submittedBy',
                'reviewedBy',
                'approvedBy',
                'rejectedBy',
                'assetItems.assignee',
                'assetItems.category',
                'assetItems.type'
            ])
            ->whereHas('assetItems', function($query) {
                if (auth()->check() && auth()->user() && !empty(auth()->user()->organization)) {
                    $query->where('organization', auth()->user()->organization);
                }
            })
            ->where(function($query) use ($asset) {
                // Try to match by ID first (if numeric), then by asset_tag
                if (is_numeric($asset)) {
                    $query->where('id', $asset);
                } else {
                    $query->where('asset_tag', $asset);
                }
            })
            ->first();

            if (!$assetWithRelations) {
                return back()->withErrors(['error' => 'Asset not found or you do not have access to this asset.']);
            }

            return Inertia::render('Assets/Show', [
                'asset' => $assetWithRelations,
                'pageTitle' => 'Asset Details',
                'pageDescription' => 'View detailed information for asset: ' . $assetWithRelations->asset_number
            ]);
        } catch (\Throwable $th) {
            return back()->withErrors(['error' => 'Failed to load asset: ' . $th->getMessage()]);
        }
    }

    /**
     * Show the history of an asset.
     */
    public function showHistory(AssetItem $assetItem)
    {
        try {
            // Get the asset item with its relationships and history
            $assetItemWithHistory = $assetItem->load([
                'assignee',
                'category',
                'type',
                'assetHistory' => function ($query) {
                    $query->orderBy('performed_at', 'desc');
                },
                'asset.region',
                'asset.district',
                'asset.facility',
                'asset.subLocation',
                'asset.fundSource'
            ]);

            return Inertia::render('Assets/AssetHistory', [
                'assetItem' => $assetItemWithHistory,
                'pageTitle' => 'Asset History',
                'pageDescription' => 'View detailed history for asset item: ' . $assetItem->asset_tag
            ]);
        } catch (\Throwable $th) {
            
            return back()->withErrors(['error' => 'Failed to load asset history: ' . $th->getMessage()]);
        }
    }

    /**
     * Show the history of a specific asset item.
     */
    public function showAssetItemHistory(AssetItem $assetItem)
    {
        try {
            // Get the asset item with its relationships and history
            $assetItemWithHistory = $assetItem->load([
                'assignee',
                'category',
                'type',
                'assetHistory' => function ($query) {
                    $query->orderBy('performed_at', 'desc');
                },
                'asset.region',
                'asset.district',
                'asset.facility',
                'asset.subLocation',
                'asset.fundSource'
            ]);

            // Get the asset for context
            $asset = $assetItem->asset;

            return Inertia::render('Assets/AssetItemHistory', [
                'asset' => $asset,
                'assetItem' => $assetItemWithHistory,
                'pageTitle' => 'Asset Item History',
                'pageDescription' => 'View detailed history for asset item: ' . $assetItem->asset_tag
            ]);
        } catch (\Throwable $th) {
            return back()->withErrors(['error' => 'Failed to load asset item history: ' . $th->getMessage()]);
        }
    }

    /**
     * Download the asset import template
     */
    public function downloadTemplate()
    {
        try {
            $headers = [
                'Asset Tag',
                'Asset Name',
                'Serial Number',
                'Category',
                'Type',
                'Fund Source',
                'Sub Location',
                'Assignee',
                'Status',
                'Value',
                'Acquisition Date'
            ];

            $sampleData = [
                [
                    'ASSET-001',
                    'Laptop Dell XPS 13',
                    'SN123456789',
                    'Electronics',
                    'Laptop',
                    'Government Budget',
                    'Mogadishu',
                    'Main Office',
                    'IT Department',
                    'John Doe',
                    'functioning',
                    '1200.00',
                    '2024-01-15'
                ],
                [
                    'ASSET-002',
                    'Office Chair',
                    'SN987654321',
                    'Furniture',
                    'Office Chair',
                    'Donor Fund',
                    'Hargeisa',
                    'Branch Office',
                    'Reception',
                    'Jane Smith',
                    'functioning',
                    '250.00',
                    '2024-01-20'
                ]
            ];

            $filename = 'assets_import_template_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            return Excel::download(new class($headers, $sampleData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
                private $data;
                private $headers;

                public function __construct($headers, $data)
                {
                    $this->headers = $headers;
                    $this->data = $data;
                }

                public function array(): array
                {
                    return $this->data;
                }

                public function headings(): array
                {
                    return $this->headers;
                }
            }, $filename);

        } catch (\Throwable $th) {
            return back()->withErrors(['error' => 'Failed to download template: ' . $th->getMessage()]);
        }
    }

    /**
     * Import assets from Excel file
     */
    public function import(Request $request)
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');

        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            ]);

            $file = $request->file('file');
            $regionId = $request->input('region_id');
            $districtId = $request->input('district_id');
            $facilityId = $request->input('facility_id');
            
            // Import the file with current user ID and selected location
            Excel::import(new AssetsImport(auth()->id(), $regionId, $districtId, $facilityId), $file);

            // Explicitly clean up the uploaded file to ensure server optimization
            if (file_exists($file->getRealPath())) {
                unlink($file->getRealPath());
            }

            return response()->json([
                'message' => 'Assets imported successfully!',
                'redirect' => route('assets.index')
            ]);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            
            foreach ($failures as $failure) {
                $errors[] = "Row " . ($failure->row() - 1) . ": " . implode(', ', $failure->errors());
            }
            
            return response()->json(['import_errors' => $errors], 422);
            
        } catch (\Throwable $th) {
            // Split by <br> if present to return as an array for the frontend "Registry"
            $message = $th->getMessage();
            $errors = str_contains($message, '<br>') ? explode('<br>', $message) : [$message];
            
            return response()->json(['import_errors' => $errors], 422);
        }
    }
}
