<?php
/**
 * Debug Sentiment Query Inconsistencies
 * 
 * Run this in PHP Tinker to verify the fixes:
 * php artisan tinker
 * include 'debug_sentiment_queries.php';
 */

use App\Models\ReviewNew;
use App\Services\Rule\RuleEngineService;

echo "\n=== SENTIMENT QUERY DEBUG SCRIPT ===\n";
echo "Testing Collection vs Query Builder consistency\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Get thresholds
$positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
$negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

echo "Thresholds:\n";
echo "  Positive: >= {$positiveThreshold}\n";
echo "  Neutral:  >= {$negativeThreshold} AND < {$positiveThreshold}\n";
echo "  Negative: < {$negativeThreshold}\n\n";

// Test with a specific business (change ID as needed)
$businessId = 1;
echo "Testing with Business ID: {$businessId}\n";
echo str_repeat('=', 80) . "\n\n";

// ========== METHOD 1: Query Builder (SQL) ==========
echo "METHOD 1: Query Builder (Direct SQL - CORRECT)\n";
echo str_repeat('-', 80) . "\n";

$qbTotal = ReviewNew::where('business_id', $businessId)
    ->whereNotNull('sentiment_score')
    ->count();

$qbPositive = ReviewNew::where('business_id', $businessId)
    ->where('sentiment_score', '>=', $positiveThreshold)
    ->count();

$qbNeutral = ReviewNew::where('business_id', $businessId)
    ->where('sentiment_score', '>=', $negativeThreshold)
    ->where('sentiment_score', '<', $positiveThreshold)
    ->count();

$qbNegative = ReviewNew::where('business_id', $businessId)
    ->where('sentiment_score', '<', $negativeThreshold)
    ->count();

$qbAvg = ReviewNew::where('business_id', $businessId)
    ->whereNotNull('sentiment_score')
    ->avg('sentiment_score');

$qbNull = ReviewNew::where('business_id', $businessId)
    ->whereNull('sentiment_score')
    ->count();

$qbZero = ReviewNew::where('business_id', $businessId)
    ->where('sentiment_score', '=', 0)
    ->count();

echo "Total Reviews: {$qbTotal}\n";
echo "Positive:      {$qbPositive}\n";
echo "Neutral:       {$qbNeutral}\n";
echo "Negative:      {$qbNegative}\n";
echo "Counts Sum:    " . ($qbPositive + $qbNeutral + $qbNegative) . "\n";
echo "NULL Scores:   {$qbNull}\n";
echo "Zero Scores:   {$qbZero}\n";
echo "Average Score: " . round($qbAvg, 3) . "\n";
echo "Math Check:    Total = Positive + Neutral + Negative? " . 
     ($qbTotal == ($qbPositive + $qbNeutral + $qbNegative) ? "✅ YES" : "❌ NO") . "\n\n";

// ========== METHOD 2: Collection (Memory - FIXED) ==========
echo "METHOD 2: Collection (In-Memory - After Fix)\n";
echo str_repeat('-', 80) . "\n";

$reviews = ReviewNew::where('business_id', $businessId)
    ->whereNotNull('sentiment_score')
    ->get();

$collTotal = $reviews->count();

// FIXED: Using 'sentiment_score' without table prefix
$collPositive = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();

$collNeutral = $reviews->where('sentiment_score', '>=', $negativeThreshold)
    ->where('sentiment_score', '<', $positiveThreshold)
    ->count();

$collNegative = $reviews->where('sentiment_score', '<', $negativeThreshold)->count();

$collAvg = $reviews->avg('sentiment_score');

$collNull = $reviews->where('sentiment_score', '===', null)->count();
$collZero = $reviews->where('sentiment_score', '===', 0)->count();

echo "Total Reviews: {$collTotal}\n";
echo "Positive:      {$collPositive}\n";
echo "Neutral:       {$collNeutral}\n";
echo "Negative:      {$collNegative}\n";
echo "Counts Sum:    " . ($collPositive + $collNeutral + $collNegative) . "\n";
echo "NULL Scores:   {$collNull}\n";
echo "Zero Scores:   {$collZero}\n";
echo "Average Score: " . round($collAvg, 3) . "\n";
echo "Math Check:    Total = Positive + Neutral + Negative? " . 
     ($collTotal == ($collPositive + $collNeutral + $collNegative) ? "✅ YES" : "❌ NO") . "\n\n";

// ========== METHOD 3: Collection (OLD BUGGY WAY) ==========
echo "METHOD 3: Collection (With Table Prefix - BUGGY/BEFORE FIX)\n";
echo str_repeat('-', 80) . "\n";

// BUGGY: Using 'review_news.sentiment_score' with table prefix
$buggyPositive = $reviews->where('review_news.sentiment_score', '>=', $positiveThreshold)->count();

$buggyNeutral = $reviews->where('review_news.sentiment_score', '>=', $negativeThreshold)
    ->where('review_news.sentiment_score', '<', $positiveThreshold)
    ->count();

$buggyNegative = $reviews->where('review_news.sentiment_score', '<', $negativeThreshold)->count();

echo "Total Reviews: {$collTotal}\n";
echo "Positive:      {$buggyPositive}\n";
echo "Neutral:       {$buggyNeutral}\n";
echo "Negative:      {$buggyNegative}\n";
echo "Counts Sum:    " . ($buggyPositive + $buggyNeutral + $buggyNegative) . "\n";
echo "Math Check:    Total = Positive + Neutral + Negative? " . 
     ($collTotal == ($buggyPositive + $buggyNeutral + $buggyNegative) ? "✅ YES" : "❌ NO (BROKEN!)") . "\n\n";

// ========== COMPARISON ==========
echo str_repeat('=', 80) . "\n";
echo "COMPARISON RESULTS\n";
echo str_repeat('=', 80) . "\n\n";

echo "1. Query Builder vs Collection (Fixed):\n";
echo "   Positive: QB={$qbPositive}, Coll={$collPositive}, Match=" . 
     ($qbPositive == $collPositive ? "✅" : "❌") . "\n";
echo "   Neutral:  QB={$qbNeutral}, Coll={$collNeutral}, Match=" . 
     ($qbNeutral == $collNeutral ? "✅" : "❌") . "\n";
echo "   Negative: QB={$qbNegative}, Coll={$collNegative}, Match=" . 
     ($qbNegative == $collNegative ? "✅" : "❌") . "\n";
echo "   Average:  QB=" . round($qbAvg, 3) . ", Coll=" . round($collAvg, 3) . 
     ", Match=" . (abs($qbAvg - $collAvg) < 0.01 ? "✅" : "❌") . "\n\n";

echo "2. Collection Fixed vs Buggy:\n";
echo "   Positive: Fixed={$collPositive}, Buggy={$buggyPositive}, Different=" . 
     ($collPositive != $buggyPositive ? "✅ (Good - fix worked!)" : "⚠️ (Same? Check data)") . "\n";
echo "   Neutral:  Fixed={$collNeutral}, Buggy={$buggyNeutral}, Different=" . 
     ($collNeutral != $buggyNeutral ? "✅ (Good - fix worked!)" : "⚠️ (Same? Check data)") . "\n";
echo "   Negative: Fixed={$collNegative}, Buggy={$buggyNegative}, Different=" . 
     ($collNegative != $buggyNegative ? "✅ (Good - fix worked!)" : "⚠️ (Same? Check data)") . "\n\n";

// ========== SAMPLE DATA ==========
echo str_repeat('=', 80) . "\n";
echo "SAMPLE SENTIMENT SCORES (First 20 reviews)\n";
echo str_repeat('=', 80) . "\n";

$samples = $reviews->take(20);
$sampleData = [];

foreach ($samples as $review) {
    $score = $review->sentiment_score ?? 'NULL';
    $label = 'Unknown';
    
    if ($score !== 'NULL') {
        if ($score >= $positiveThreshold) {
            $label = 'Positive';
        } elseif ($score >= $negativeThreshold) {
            $label = 'Neutral';
        } else {
            $label = 'Negative';
        }
    }
    
    $sampleData[] = [
        'id' => $review->id,
        'score' => $score,
        'label' => $label
    ];
}

echo sprintf("%-10s %-15s %-15s\n", 'Review ID', 'Score', 'Label');
echo str_repeat('-', 40) . "\n";

foreach ($sampleData as $data) {
    echo sprintf("%-10s %-15s %-15s\n", 
        $data['id'], 
        $data['score'] === 'NULL' ? 'NULL' : number_format($data['score'], 2),
        $data['label']
    );
}

// ========== SCORE DISTRIBUTION ==========
echo "\n" . str_repeat('=', 80) . "\n";
echo "DETAILED SCORE DISTRIBUTION\n";
echo str_repeat('=', 80) . "\n";

$distribution = [
    'null' => 0,
    'zero' => 0,
    'very_low' => 0,    // 0 < x < 0.2
    'low' => 0,         // 0.2 <= x < 0.4
    'neutral' => 0,     // 0.4 <= x < 0.7
    'positive' => 0,    // 0.7 <= x < 0.9
    'very_positive' => 0 // 0.9 <= x <= 1.0
];

foreach ($reviews as $review) {
    $score = $review->sentiment_score;
    
    if ($score === null) {
        $distribution['null']++;
    } elseif ($score == 0) {
        $distribution['zero']++;
    } elseif ($score < 0.2) {
        $distribution['very_low']++;
    } elseif ($score < 0.4) {
        $distribution['low']++;
    } elseif ($score < 0.7) {
        $distribution['neutral']++;
    } elseif ($score < 0.9) {
        $distribution['positive']++;
    } else {
        $distribution['very_positive']++;
    }
}

echo "NULL scores:        {$distribution['null']}\n";
echo "Zero scores (0.0):  {$distribution['zero']}\n";
echo "Very Low (0-0.2):   {$distribution['very_low']}\n";
echo "Low (0.2-0.4):      {$distribution['low']}\n";
echo "Neutral (0.4-0.7):  {$distribution['neutral']}\n";
echo "Positive (0.7-0.9): {$distribution['positive']}\n";
echo "Very Pos (0.9-1.0): {$distribution['very_positive']}\n";
echo "Total:              " . array_sum($distribution) . "\n\n";

// ========== VALIDATION ==========
echo str_repeat('=', 80) . "\n";
echo "VALIDATION SUMMARY\n";
echo str_repeat('=', 80) . "\n\n";

$allPass = true;

// Check 1: Query Builder math
$qbMathCheck = ($qbTotal == ($qbPositive + $qbNeutral + $qbNegative));
echo "✓ Query Builder Math: " . ($qbMathCheck ? "✅ PASS" : "❌ FAIL") . "\n";
$allPass = $allPass && $qbMathCheck;

// Check 2: Collection math
$collMathCheck = ($collTotal == ($collPositive + $collNeutral + $collNegative));
echo "✓ Collection Math: " . ($collMathCheck ? "✅ PASS" : "❌ FAIL") . "\n";
$allPass = $allPass && $collMathCheck;

// Check 3: QB vs Collection match
$qbCollMatch = ($qbPositive == $collPositive && $qbNeutral == $collNeutral && $qbNegative == $collNegative);
echo "✓ QB vs Collection Match: " . ($qbCollMatch ? "✅ PASS" : "❌ FAIL") . "\n";
$allPass = $allPass && $qbCollMatch;

// Check 4: Buggy version different
$buggyDifferent = ($collNeutral != $buggyNeutral || $collPositive != $buggyPositive);
echo "✓ Fix Changed Results: " . ($buggyDifferent ? "✅ PASS (Fix worked!)" : "⚠️ WARN (Check data)") . "\n";

// Check 5: Minimum average validation
$minPossibleAvg = $collTotal > 0 ? 
    ($collPositive * $positiveThreshold + $collNeutral * $negativeThreshold) / $collTotal : 0;
$avgValid = ($collAvg >= ($minPossibleAvg - 0.01));
echo "✓ Average >= Minimum: " . ($avgValid ? "✅ PASS" : "❌ FAIL") . 
     " (Actual: " . round($collAvg, 3) . ", Min: " . round($minPossibleAvg, 3) . ")\n";
$allPass = $allPass && $avgValid;

echo "\n" . str_repeat('=', 80) . "\n";
if ($allPass) {
    echo "🎉 ALL CHECKS PASSED! Sentiment queries are now consistent.\n";
} else {
    echo "⚠️ SOME CHECKS FAILED! Review the results above.\n";
}
echo str_repeat('=', 80) . "\n\n";

echo "Debug completed at " . date('Y-m-d H:i:s') . "\n";
