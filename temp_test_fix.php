<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Collection;

$product = Product::with(['items', 'dosage'])->whereHas('items')->first();

if ($product) {
    echo "Testing product: " . $product->name . "\n";
    $reorderLevel = 10000; // Force low stock for testing
    $totalQty = $product->items->sum('quantity');
    $itemsToNotify = new Collection();

    if ($reorderLevel > 0 && $totalQty <= $reorderLevel) {
        $uom = $product->items->first()?->uom ?? ($product->dosage->name ?? '');
        
        $itemsToNotify->push((object)[
            'product' => $product,
            'quantity' => $totalQty,
            'uom' => $uom,
            'current_reorder_level' => $reorderLevel
        ]);
    }

    echo "Items to notify count: " . $itemsToNotify->count() . "\n";
    foreach ($itemsToNotify as $item) {
        echo " - Item Product Name: " . $item->product->name . "\n";
        echo " - Item QTY: " . $item->quantity . " " . $item->uom . "\n";
        echo " - Reorder Level: " . $item->current_reorder_level . "\n";
    }
} else {
    echo "No products with items found.\n";
}
