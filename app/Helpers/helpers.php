<?php

use Carbon\Carbon;




if (!function_exists('getDistanceMeters')) {
    function getDistanceMeters($lat1, $lon1, $lat2, $lon2)
    {
        $earth_radius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth_radius * $c;
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
        }
        ;
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


if (!function_exists('getDateRangeByPeriod')) {
    function getDateRangeByPeriod($period)
    {
        $now = Carbon::now();

        return match ($period) {
            // Days
            'yesterday' => [
                'start' => $now->copy()->subDay()->startOfDay(),
                'end' => $now->copy()->subDay()->endOfDay(),
                'daysOffset' => 1
            ],
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'daysOffset' => 1
            ],
            'last_7_days' => [
                'start' => $now->copy()->subDays(7)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'daysOffset' => 7
            ],
            'last_30_days' => [
                'start' => $now->copy()->subDays(30)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'daysOffset' => 30
            ],
            'last_90_days' => [
                'start' => $now->copy()->subDays(90)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'daysOffset' => 90
            ],
            'next_7_days' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->addDays(7)->endOfDay(),
                'daysOffset' => 7
            ],
            'next_30_days' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->addDays(30)->endOfDay(),
                'daysOffset' => 30
            ],
            'next_90_days' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->addDays(90)->endOfDay(),
                'daysOffset' => 90
            ],

            // Weeks
            'this_week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfDay(),
                'daysOffset' => 7
            ],
            'last_week' => [
                'start' => $now->copy()->subWeek()->startOfWeek(),
                'end' => $now->copy()->subWeek()->endOfWeek(),
                'daysOffset' => 7
            ],
            'next_week' => [
                'start' => $now->copy()->addWeek()->startOfWeek(),
                'end' => $now->copy()->addWeek()->endOfWeek(),
                'daysOffset' => 7
            ],

            // Months
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfDay(),
                'daysOffset' => 30
            ],
            'last_month' => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end' => $now->copy()->subMonth()->endOfMonth(),
                'daysOffset' => 30
            ],
            'next_month' => [
                'start' => $now->copy()->addMonth()->startOfMonth(),
                'end' => $now->copy()->addMonth()->endOfMonth(),
                'daysOffset' => 30
            ],

            // Quarters
            'this_quarter' => [
                'start' => $now->copy()->startOfQuarter(),
                'end' => $now->copy()->endOfDay(),
                'daysOffset' => 90
            ],
            'last_quarter' => [
                'start' => $now->copy()->subQuarter()->startOfQuarter(),
                'end' => $now->copy()->subQuarter()->endOfQuarter(),
                'daysOffset' => 90
            ],
            'next_quarter' => [
                'start' => $now->copy()->addQuarter()->startOfQuarter(),
                'end' => $now->copy()->addQuarter()->endOfQuarter(),
                'daysOffset' => 90
            ],

            // Years
            'this_year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfDay(),
                'daysOffset' => 365
            ],
            'last_year' => [
                'start' => $now->copy()->subYear()->startOfYear(),
                'end' => $now->copy()->subYear()->endOfYear(),
                'daysOffset' => 365
            ],
            'next_year' => [
                'start' => $now->copy()->addYear()->startOfYear(),
                'end' => $now->copy()->addYear()->endOfYear(),
                'daysOffset' => 365
            ],

            // Default fallback (last 30 days)
            default => [
                'start' => $now->copy()->subDays(30)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'daysOffset' => 30
            ]
        };
    }
}
























































