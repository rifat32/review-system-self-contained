<?php

use App\Services\Rule\RuleReportService;
use App\Models\Business;

// Assuming business ID 1 exists
$businessId = 1;
$reportService = app(RuleReportService::class);

try {
    $boxes = $reportService->getDashboardBoxes($businessId, 'last_30_days');

    echo "Dashboard Boxes Response:\n";
    echo json_encode($boxes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    // Simple assertions
    $keys = array_column($boxes, 'key');
    $requiredKeys = ['TOTAL_REVIEWS', 'AVG_RATING', 'CSAT_SCORE', 'SENTIMENT_ANALYSIS', 'REPEAT_ISSUE'];

    foreach ($requiredKeys as $key) {
        if (in_array($key, $keys)) {
            echo "[PASS] Found key: $key\n";
        } else {
            echo "[FAIL] Missing key: $key\n";
        }
    }
} catch (\Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
