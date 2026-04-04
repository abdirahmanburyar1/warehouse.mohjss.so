<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseAmc extends Model
{
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'month_year',
        'quantity'
    ];

    protected $casts = [
        'quantity' => 'integer'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // Helper method to get or create AMC record for a product
    public static function getOrCreate($productId, $monthYear, $warehouseId = null)
    {
        return static::firstOrCreate(
            [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'month_year' => $monthYear
            ],
            ['quantity' => 0]
        );
    }

    // Helper method to update consumption for current month
    public static function updateConsumption($productId, $quantity, $warehouseId = null)
    {
        $monthYear = now()->format('Y-m');
        $warehouseId = $warehouseId ?: auth()->user()->warehouse_id;
        
        return static::updateOrCreate(
            [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'month_year' => $monthYear
            ],
            [
                'quantity' => $quantity
            ]
        );
    }

    // Calculate average monthly consumption
    public static function calculateAMC($productId, $months = 3, $warehouseId = null)
    {
        $endDate = now();
        $startDate = now()->subMonths($months);
        $warehouseId = $warehouseId ?: auth()->user()->warehouse_id;

        $consumptions = static::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('month_year', '>=', $startDate->format('Y-m'))
            ->where('month_year', '<=', $endDate->format('Y-m'))
            ->get();

        if ($consumptions->isEmpty()) {
            return 0;
        }

        return ceil($consumptions->avg('quantity'));
    }
}
