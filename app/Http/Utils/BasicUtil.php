
<?php

namespace App\Http\Utils;

use App\Models\Business;
use App\Models\ReviewNew;
use Exception;


trait BasicUtil
{


   public function applyThresholdAndSetStatus($businessId)
{
    $business = Business::find($businessId);
    if (!$business) return;

    // Calculate average rating of all reviews for that business
    $average_rating = ReviewNew::where('business_id', $businessId)->avg('rate');

    // Update latest review status based on threshold
    $latest_review = ReviewNew::where('business_id', $businessId)->latest()->first();

    if ($latest_review) {
        if ($average_rating >= $business->threshold_rating) {
            $latest_review->status = 'published';
        } else {
            $latest_review->status = 'pending';
        }
        $latest_review->save();
    }
}




 



}
