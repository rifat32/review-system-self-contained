<?php

// This script audits the codebase for ReviewNew queries to ensure exactly one date filter is applied.

$dir = 'app';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$phpFiles = new RegexIterator($iterator, '/\.php$/');

$violations = [];

foreach ($phpFiles as $file) {
    if ($file->isDir()) continue;
    $filePath = $file->getRealPath();
    if (strpos($filePath, 'Models/ReviewNew.php') !== false) continue;

    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);

    $currentQuery = null;
    $hasGlobalDefault = false;
    $hasGlobalIgnore = false;
    $hasLocalFilter = false;
    $hasDynamicIgnore = false;
    $startLine = 0;

    foreach ($lines as $i => $line) {
        $lineNumber = $i + 1;

        if (preg_match('/ReviewNew::|ReviewNew\->/i', $line)) {
            if ($currentQuery) {
                checkViolations($filePath, $startLine, $hasGlobalDefault, $hasGlobalIgnore, $hasLocalFilter, $hasDynamicIgnore, $violations);
            }
            $currentQuery = $line;
            $startLine = $lineNumber;
            $hasGlobalDefault = false;
            $hasGlobalIgnore = false;
            $hasLocalFilter = false;
            $hasDynamicIgnore = false;
        }

        if ($currentQuery) {
            // Check for globalReviewFilters with 0, 1 or empty args (defaulting to ignoreDateRange = false)
            if (preg_match('/globalReviewFilters\s*\(\s*([01])?\s*\)/i', $line)) {
                $hasGlobalDefault = true;
            }
            // Check for globalReviewFilters where the 3rd argument is true|1
            if (preg_match('/globalReviewFilters\s*\([^,]+,[^,]+,\s*(true|1)\s*\)/i', $line)) {
                $hasGlobalIgnore = true;
            }
            // Detect dynamic boolean like $dateRange !== null as the 3rd argument
            if (preg_match('/globalReviewFilters\s*\([^,]+,[^,]+,\s*\$[a-zA-Z0-9_]+\s*!==?\s*null\s*\)/i', $line)) {
                $hasDynamicIgnore = true;
            }
            // Check for manual date filters
            if (preg_match('/whereDate|whereBetween|where\s*\(\s*[\'"]created_at[\'"]/i', $line)) {
                $hasLocalFilter = true;
            }

            // Query ends with ; or return or assigned
            if (strpos($line, ';') !== false || strpos($line, 'return') !== false) {
                checkViolations($filePath, $startLine, $hasGlobalDefault, $hasGlobalIgnore, $hasLocalFilter, $hasDynamicIgnore, $violations);
                $currentQuery = null;
            }
        }
    }
}

function checkViolations($file, $line, $hasGlobalDefault, $hasGlobalIgnore, $hasLocalFilter, $hasDynamicIgnore, &$violations)
{
    if ($hasGlobalDefault && $hasLocalFilter) {
        $violations[] = "[DUPLICATE] $file:$line - Both globalReviewFilters(default) and local date filter found.";
    }

    if ($hasGlobalIgnore && !$hasLocalFilter) {
        $violations[] = "[MISSING LOCAL] $file:$line - globalReviewFilters(ignoreDateRange=true) used but no local filter found.";
    }

    if ($hasDynamicIgnore && !$hasLocalFilter) {
        $violations[] = "[CHECK DYNAMIC] $file:$line - Dynamic ignoreDateRange used. Ensure local filter is applied conditionally.";
    }
}

echo implode("\n", $violations) . "\n";
echo "Audit complete.\n";
