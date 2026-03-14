<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OrderItem;
use App\Services\AmcCalculationService;

// Mock item_id 27 (from logs)
$itemId = 27;
$orderItem = OrderItem::find($itemId);

if (!$orderItem) {
    echo "Item $itemId not found\n";
    exit;
}

$facilityId = $orderItem->order->facility_id;
$productId = $orderItem->product_id;

$amcService = new AmcCalculationService();
$amcData = $amcService->calculateAmc($facilityId, $productId);
$amc = (float)($amcData['amc'] ?? 0);

echo "AMC for product $productId at facility $facilityId: $amc\n";
echo "Formula: Number of Days = (Qty / AMC) * 30\n";
echo "Formula: Qty = (Days * AMC) / 30\n\n";

if ($amc > 0) {
    $testQty = 100;
    $expectedDays = ceil(($testQty / $amc) * 30);
    echo "Test Qty: $testQty -> Expected Days: $expectedDays\n";

    $testDays = 30;
    $expectedQty = ceil(($testDays * $amc) / 30);
    echo "Test Days: $testDays -> Expected Qty: $expectedQty\n";
} else {
    echo "AMC is 0, fallback logic will be used.\n";
}
