<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leaflet extends Model
{
    use HasFactory;

    protected $fillable = [
        "business_id",
        "thumbnail",
        "leaflet_data",
        "title",
        "type"
    ];

    /**
     * Scope a query to filter leaflets based on request parameters.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query)
    {
        $request = request();

        if (!empty($request->business_id)) {
            $query->where('business_id', $request->business_id);
        }

        if (!empty($request->type)) {
            $query->where('type', $request->type);
        }

        return $query;
    }
}
