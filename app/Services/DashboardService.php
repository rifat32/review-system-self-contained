<?php

namespace App\Services;

use App\Http\Utils\DateRangeUtil;
use App\Models\Business;
use App\Models\User;

class DashboardService
{
    public function getCustomersByPeriod($period, $business)
    {
        $dateRange = DateRangeUtil::getDateRange($period);
        $start = $dateRange['start_date'];
        $end = $dateRange['end_date'];

        // Placeholder logic - replace with actual customer filtering based on business and date range
        $first_time_customers = User::distinct()->get();
        $returning_customers = User::distinct()->get();

        // Return the results
        return [
            'first_time_customers' => $first_time_customers,
            'returning_customers' => $returning_customers,
        ];
    }
}
