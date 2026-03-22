<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use App\Mail\LowStockNotification;

// Mock Mail to capture the notification
Mail::fake();

// We need to bypass the time check and manually run the logic from handle()
// Get all products and calculate their metrics
$allProducts = Product::with(['items', 'category', 'dosage', 'items.warehouse'])->get();
$itemsToNotify = new Collection();

foreach ($allProducts as $product) {
    $metrics = $product->calculateInventoryMetrics();
    $reorderLevel = $metrics['reorder_level'] ?? 0;
    $totalQty = $product->items->sum('quantity');

    if ($reorderLevel > 0 && $totalQty <= $reorderLevel) {
        $uom = $product->items->first()?->uom ?? ($product->dosage->name ?? '');
        
        $itemsToNotify->push((object)[
            'product' => $product,
            'quantity' => $totalQty,
            'uom' => $uom,
            'current_reorder_level' => $reorderLevel
        ]);
    }
}

echo "Total products low on stock: " . $itemsToNotify->count() . "\n\n";

if ($itemsToNotify->count() > 0) {
    echo str_pad("Product Name", 50) . " | " . str_pad("Total QTY", 10) . " | " . str_pad("Reorder Level", 15) . "\n";
    echo str_repeat("-", 85) . "\n";
    foreach ($itemsToNotify as $item) {
        echo str_pad($item->product->name, 50) . " | " . str_pad($item->quantity . " " . $item->uom, 10) . " | " . str_pad($item->current_reorder_level . " " . $item->uom, 15) . "\n";
    }
} else {
    echo "No items found below reorder level.\n";
}
