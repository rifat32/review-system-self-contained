<?php






// Calculate Aggregated Sentiment
if (!function_exists('calculateAggregatedSentiment')) {
    function calculateAggregatedSentiment($reviews)
    {
        return App\Helpers\AIProcessor::calculateAggregatedSentiment($reviews);
    }
}

// Generate Staff Suggestions
if (!function_exists('generateStaffSuggestions')) {
    function generateStaffSuggestions($weaknesses)
    {
        $suggestions = [];

        foreach ($weaknesses as $weakness) {
            switch ($weakness) {
                case 'communication':
                    $suggestions[] = 'Needs better communication skills training';
                    break;
                case 'service_speed':
                    $suggestions[] = 'Requires efficiency and time management training';
                    break;
                case 'product_knowledge':
                    $suggestions[] = 'Needs product knowledge workshop';
                    break;
                case 'attitude':
                    $suggestions[] = 'Customer service excellence training recommended';
                    break;
            }
        }

        return $suggestions;
    }
}

if (!function_exists('log_message')) {
    function log_message(mixed $message, string $fileName = 'debug.log'): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $fullPath = storage_path("logs/{$fileName}");

        // Convert non-string messages to JSON
        if (!is_string($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        file_put_contents($fullPath, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}

if (!function_exists('retrieve_data')) {
    function retrieve_data($query, $orderBy = 'created_at', $tableName = '')
    {
        // Get order column and sort order
        if (request()->filled('order_by')) {
            $orderBy = request()->input('order_by');
        };
        // Handle order_by safely (default to id if empty)
        if (request()->filled('order_by') && request()->input('order_by') !== '') {
            $orderBy = request()->input('order_by');
        } else {
            $orderBy = 'id'; // fallback default
        }




        $sortOrder = strtoupper(request()->input('sort_order', 'DESC'));

        // Ensure sort_order is valid
        if (!in_array($sortOrder, ['ASC', 'DESC'])) {
            $sortOrder = 'DESC';
        }

        // Add table prefix if not included
        // if (strpos($orderBy, '.') === false) {
        //     $orderBy = $tableName . '.' . $orderBy;
        // }

        // Apply ordering
        $query = $query->orderBy($orderBy, $sortOrder);

        // Pagination setup
        $perPage = request()->input('per_page');
        $currentPage = request()->input('page', 1);
        $skip = 0;
        $total = 0;
        $totalPages = 1;

        if ($perPage) {
            $paginated = $query->paginate($perPage, ['*'], 'page', $currentPage);

            $data = $paginated->items();
            $skip = ($currentPage - 1) * $perPage;
            $total = $paginated->total();
            $perPage = $paginated->perPage();
            $currentPage = $paginated->currentPage();
            $totalPages = $paginated->lastPage();
        } else {
            $data = $query->get();
            $total = $data->count();
        }

        // Meta info
        $meta = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'skip' => $skip,
            'total_pages' => $totalPages,
        ];

        // Return data with meta
        return [
            'data' => $data,
            'meta' => $meta,
        ];
    }
}



























































