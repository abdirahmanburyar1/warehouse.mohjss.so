<?php

function processAmcCalculation($monthsData) {
    $monthsCount = count($monthsData);
    $selectedMonths = [];
    $amc = 0;
    $calculation = '';

    if ($monthsCount >= 3) {
        $pool = $monthsData;
        $candidates = array_splice($pool, 0, 3);
        $iteration = 0;
        $maxIterations = 10;

        while ($iteration < $maxIterations) {
            $iteration++;
            $count = count($candidates);
            
            $avg = array_sum(array_column($candidates, 'consumption')) / $count;
            echo "Iteration $iteration: Current Set [" . implode(", ", array_column($candidates, 'consumption')) . "] | Avg: " . round($avg, 2) . "\n";
            
            $passed = [];
            $failed = [];
            $threshold = $avg * 0.7;
            
            foreach ($candidates as $c) {
                $dev = abs($avg - $c['consumption']);
                $pct = $avg > 0 ? ($dev / $avg) * 100 : 0;
                if ($pct <= 70) {
                    $passed[] = $c;
                    echo "  - Month " . $c['month'] . " (" . $c['consumption'] . ") Passed (Dev: " . round($pct, 2) . "%)\n";
                } else {
                    $failed[] = $c;
                    echo "  - Month " . $c['month'] . " (" . $c['consumption'] . ") Failed (Dev: " . round($pct, 2) . "%)\n";
                }
            }

            if (empty($failed)) {
                $selectedMonths = $candidates;
                $amc = $avg;
                echo "Final Decision: STABILIZED. AMC = " . round($amc, 2) . "\n";
                break;
            }

            $candidates = $passed;
            if (empty($candidates)) {
                echo "  - ACTION (Scenario 1): All months in set failed. Picking two closest to average.\n";
                usort($failed, function($a, $b) use ($avg) {
                    return abs($avg - $a['consumption']) <=> abs($avg - $b['consumption']);
                });
                $candidates = array_slice($failed, 0, 2);
            } else {
                echo "  - ACTION: Dropping " . count($failed) . " month(s) that failed.\n";
            }

            $needed = 3 - count($candidates);
            if ($needed > 0 && !empty($pool)) {
                $added = array_splice($pool, 0, $needed);
                echo "  - ACTION: Refilling $needed month(s) from history: [" . implode(", ", array_column($added, 'consumption')) . "]\n";
                $candidates = array_merge($candidates, $added);
            } else {
                $mostRecentThree = array_slice($monthsData, 0, 3);
                $amc = array_sum(array_column($mostRecentThree, 'consumption')) / count($mostRecentThree);
                echo "Final Decision (Scenario 2): FAILED TO STABILIZE AFTER YEAR. Fallback to latest 3 months: " . round($amc, 2) . "\n";
                break;
            }
            echo "\n";
        }
    }
}

// Test Data
$data = [
    ['month' => 'Feb', 'consumption' => 10],   // Month 1 (Latest)
    ['month' => 'Jan', 'consumption' => 5000], // Month 2
    ['month' => 'Dec', 'consumption' => 10],   // Month 3
    ['month' => 'Nov', 'consumption' => 5000], // Month 4
    ['month' => 'Oct', 'consumption' => 10],   // Month 5
    ['month' => 'Sep', 'consumption' => 10],   // Month 6
];

processAmcCalculation($data);
