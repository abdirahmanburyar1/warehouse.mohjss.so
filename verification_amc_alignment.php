<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AmcCalculationService;

$service = new AmcCalculationService();

// Mock data similar to test_amc.php
$data = [
    ['month' => '2026-02', 'consumption' => 10],   // Month 1 (Latest)
    ['month' => '2026-01', 'consumption' => 5000], // Month 2 (Outlier)
    ['month' => '2025-12', 'consumption' => 10],   // Month 3
    ['month' => '2025-11', 'consumption' => 5000], // Month 4 (Outlier)
    ['month' => '2025-10', 'consumption' => 10],   // Month 5
    ['month' => '2025-09', 'consumption' => 10],   // Month 6
];

$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('processAmcCalculation');
$method->setAccessible(true);

echo "Testing AMC Calculation with Outliers...\n";
$result = $method->invoke($service, $data, 0);

echo "AMC Result: " . $result['amc'] . "\n";
echo "Selected Months: " . count($result['selectedMonths']) . "\n";
echo "Calculation: " . $result['calculation'] . "\n";

if ($result['amc'] == 10) {
    echo "SUCCESS: Outliers were successfully filtered, AMC is 10.\n";
} else {
    echo "FAILURE: Expected AMC 10, got " . $result['amc'] . "\n";
}
