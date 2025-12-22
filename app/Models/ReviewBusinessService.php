<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewBusinessService extends Model
{
    

    

    protected $fillable = [
        'review_id',
        'business_service_id',
        'business_area_id',
    ];

   
    public function business_service()
    {
        return $this->belongsTo(BusinessService::class, 'business_service_id');
    }

    public function business_area()
    {
        return $this->belongsTo(BusinessArea::class, 'business_area_id');
    }


    
}
