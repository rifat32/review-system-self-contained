<?php

namespace App\Http\Utils;

use Carbon\Carbon;

class DateRangeUtil
{
    public static function getDateRange($period)
    {
        switch ($period) {
            case 'today':
                $start = Carbon::today();
                $end = Carbon::today();
                break;
            case 'this_week':
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();
                break;
            case 'this_month':
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                break;
            case 'next_week':
                $start = Carbon::now()->addWeek()->startOfWeek();
                $end = Carbon::now()->addWeek()->endOfWeek();
                break;
            case 'next_month':
                $start = Carbon::now()->addMonth()->startOfMonth();
                $end = Carbon::now()->addMonth()->endOfMonth();
                break;
            case 'previous_week':
                $start = Carbon::now()->subWeek()->startOfWeek();
                $end = Carbon::now()->subWeek()->endOfWeek();
                break;
            case 'previous_month':
                $start = Carbon::now()->subMonth()->startOfMonth();
                $end = Carbon::now()->subMonth()->endOfMonth();
                break;
            default:
                $start = "";
                $end = "";
        }

        return [
            'start_date' => $start,
            'end_date' => $end,
        ];
    }
}
