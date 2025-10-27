<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;



class FormatDatesInRequest
{
    public function handle($request, Closure $next)
    {
        $data = $this->formatDates($request->all());
        $request->merge($data);

        return $next($request);
    }

    private function isDateOrDateTimeFormat($value)
    {
        if (!is_string($value)) {
            return false;
        }

        // Check for date format
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
            return 'date';
        }

        // Check for date-time format
        if (preg_match('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}(:\d{2})?$/', $value)) {
            return 'datetime';
        }

        return false;
    }

    private function formatDates($data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->formatDates($value);
            } else {
                $formatType = $this->isDateOrDateTimeFormat($value);
                if ($formatType === 'date') {
                    $data[$key] = Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
                } elseif ($formatType === 'datetime') {
                    $data[$key] = Carbon::createFromFormat('d-m-Y H:i:s', $value)->format('Y-m-d H:i:s');
                }
            }
        }

        return $data;
    }
}
