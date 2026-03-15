<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\Dosage;
use App\Models\Inventory;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\SubCategory;
use App\Models\WarehouseAmc;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'productID',
        'name',
        'category_id',
        'dosage_id',
        // 'movement',
        'is_active',
        'tracert_type',
        'supply_class',
    ];

    protected $casts = [
        'tracert_type' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            // Find the highest productID in the database
            $maxProductId = self::max('productID');
            
            // If there are existing products, increment the highest productID
            if ($maxProductId) {
                $nextId = (int)$maxProductId + 1;
            } else {
                // Start from 1 if no products exist
                $nextId = 1;
            }
            
            // Format as 6-digit number with leading zeros
            $product->productID = str_pad($nextId, 6, '0', STR_PAD_LEFT);
        });
    }



    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    public function dosage()
    {
        return $this->belongsTo(Dosage::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class, 'sub_category_id');
    }

    /**
     * Get the supply items that contain this product.
     */
    public function supplyItems()
    {
        return $this->hasMany(SupplyItem::class);
    }

    /**
     * Get the supplies that contain this product.
     */
    public function supplies()
    {
        return $this->belongsToMany(Supply::class, 'supply_items')
            ->withPivot(['quantity', 'status'])
            ->withTimestamps();
    }

    // reorderLevel
    public function reorderLevel()
    {
        return $this->hasOne(ReorderLevel::class);
    }

    /**
     * Get the inventories for the product.
     */
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function items(){
        return $this->hasMany(InventoryItem::class);
    }

    public function eligible(){
        return $this->hasMany(EligibleItem::class);
    }

    public function facilityInventories(){
        return $this->hasMany(FacilityInventory::class);
    }


    /**
     * Get the warehouse AMCs for this product.
     */
    public function warehouseAmcs()
    {
        return $this->hasMany(\App\Models\WarehouseAmc::class);
    }

    /**
     * Calculate AMC using exact 70% deviation screening formula
     * Formula: Find 3 consecutive months that pass ≤70% deviation threshold
     * Example: Jan=3000, Feb=2000, Mar=3000, Apr=6000, May=2500, Jun=300
     * Step 1: Take closest 3 months (Jun=300, May=2500, Apr=6000)
     * Step 2: Calculate average: (300+2500+6000)/3 = 2933
     * Step 3: Check each month: |300-2933|/2933*100 = 89.7% > 70% (FAILED)
     * Step 4: Reselect 3 months including passed ones: May=2500, Apr=6000, Mar=3000
     * Step 5: Calculate average: (2500+6000+3000)/3 = 3833
     * Step 6: Check each month: |2500-3833|/3833*100 = 34.8% ≤ 70% (PASSED)
     * Step 7: Final AMC = 3833
     */
    public function calculateAMC()
    {
        try {
            $amcService = new \App\Services\WarehouseAmcCalculationService();
            $result = $amcService->calculateAmc($this->id);
            return $result['amc'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function calculateBufferStock()
    {
        try {
            $amcService = new \App\Services\WarehouseAmcCalculationService();
            $result = $amcService->calculateAmc($this->id);
            
            $amc = $result['amc'];
            $maxMc = $result['max_mc'];
            
            if ($amc == 0) return 0;
            
            $avgLeadTime = 3.5;
            $maxLeadTime = 6;
            
            // Formula: (MAX Monthly usageUsage among the typical three months of AMC) X Max Lead Time in Months) - (AMC X Average Lead Time in Months)
            $bufferStock = ($maxMc * $maxLeadTime) - ($amc * $avgLeadTime);
            
            return round(max(0, $bufferStock), 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate reorder level: (AMC × 3) + Buffer Stock
     */
    public function calculateReorderLevel()
    {
        try {
            $amc = $this->calculateAMC();
            if ($amc == 0) return 0;
            
            $avgLeadTime = 3.5;
            $safetyStock = $this->calculateBufferStock();
            
            // Formula: (AMC x Average Lead Time in Months) + Safety Stock
            $reorderLevel = ($amc * $avgLeadTime) + $safetyStock;
            return round($reorderLevel, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function calculateInventoryMetrics()
    {
        try {
            $amcService = new \App\Services\WarehouseAmcCalculationService();
            $result = $amcService->calculateAmc($this->id);
            
            if ($result['amc'] > 0) {
                $amc = $result['amc'];
                $safetyStock = $this->calculateBufferStock();
                $reorderLevel = $this->calculateReorderLevel();
                
                return [
                    'amc' => $amc,
                    'buffer_stock' => $safetyStock,
                    'reorder_level' => $reorderLevel
                ];
            }
            
            return [
                'amc' => 0,
                'buffer_stock' => 0,
                'reorder_level' => 0
            ];
        } catch (\Exception $e) {
            return [
                'amc' => 0,
                'buffer_stock' => 0,
                'reorder_level' => 0
            ];
        }
    }

    /**
     * Get the inventory structure for frontend
     */
    public function getInventoryStructureAttribute()
    {
        // Get inventory items directly for this product
        $inventoryItems = $this->items;
        
        // Calculate all inventory metrics in one optimized call
        $metrics = $this->calculateInventoryMetrics();
        
        // Calculate the current status based on total quantity and reorder level
        // EXACTLY matching the frontend getInventoryStatus logic
        $totalQuantity = $inventoryItems->sum('quantity');
        $reorderLevel = $metrics['reorder_level'];
        
        $status = 'in_stock'; // default
        
        // Check if completely out of stock first
        if ($totalQuantity <= 0) {
            $status = 'out_of_stock';
        } elseif ($metrics['amc'] > 0 && $totalQuantity > ($metrics['amc'] * 8)) {
            // Over-stock for Warehouse is > AMC X 8
            $status = 'over_stock';
        } elseif ($reorderLevel <= 0) {
            $status = 'in_stock';
        } else {
            // Low Stock = 0 < Total Qty ≤ (Reorder Level * 0.7) (Critical)
            $lowStockThreshold = $reorderLevel * 0.7;
            
            if ($totalQuantity <= $lowStockThreshold) {
                $status = 'low_stock';
            } elseif ($totalQuantity <= $reorderLevel) {
                // Reorder Level = (Reorder Level * 0.7) < Total Qty ≤ Reorder Level
                $status = 'reorder_level';
            } else {
                $status = 'in_stock';
            }
        }
        
        return [
            'id' => $this->id,
            'product_id' => $this->id,
            'items' => $inventoryItems, // This will be empty array if no items exist
            'amc' => $metrics['amc'],
            'buffer_stock' => $metrics['buffer_stock'],
            'reorder_level' => $metrics['reorder_level'],
            'status' => $status, // Add calculated status
            'product' => [
                'id' => $this->id,
                'name' => $this->name,
                'uom' => $inventoryItems->first()->uom,
                'category' => [
                    'id' => $this->category->id ?? null,
                    'name' => $this->category->name ?? null
                ],
                'dosage' => [
                    'id' => $this->dosage->id ?? null,
                    'name' => $this->dosage->name ?? null
                ]
            ]
        ];
    }


}
