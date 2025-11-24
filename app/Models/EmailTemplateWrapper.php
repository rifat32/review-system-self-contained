<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplateWrapper extends Model
{
    use HasFactory;

    /**
     * Scope a query to filter email template wrappers based on request parameters.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query)
    {
        $request = request();

        if (!empty($request->search_key)) {
            $query->where(function ($q) use ($request) {
                $term = $request->search_key;
                $q->where("type", "like", "%" . $term . "%");
            });
        }

        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        return $query;
    }
}
