<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackingListDifference extends Model
{
    use HasFactory;

    protected $table = 'packing_list_differences';

    protected $fillable = [
        'packing_list_item_id', // Updated field name
        'order_item_id', // For order-based differences
        'transfer_item_id', // For transfer-based differences
        'inventory_allocation_id', // Links difference to allocation (required for transfer backorder modal)
        'back_order_id',
        'product_id',
        'quantity',
        'original_quantity',
        'finalized',
        'status',
        'notes'
    ];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function transferItem()
    {
        return $this->belongsTo(TransferItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function backOrder()
    {
        return $this->belongsTo(BackOrder::class);
    }

    public function inventoryAllocation()
    {
        return $this->belongsTo(InventoryAllocation::class, 'inventory_allocation_id');
    }

    public function packingListItem()
    {
        return $this->belongsTo(PackingListItem::class, 'packing_list_item_id');
    }
} 