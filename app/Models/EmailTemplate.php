<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;
    protected $fillable = [
        "type",
        "template",
        "is_active"
    ];

    /**
     * Scope a query to filter email templates based on request parameters.
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
