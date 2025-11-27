<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Star;
use App\Models\ReviewValueNew;
use Illuminate\Http\Request;

class BusinessService
{
    /**
     * Enrich business object with ratings and timing information
     *
     * @param Business $business
     * @param int $dayOfWeek
     * @param Request $request
     * @return Business
     */
    public function enrichBusinessWithRatingsAndTiming($business, $dayOfWeek, $request)
    {
        $totalCount = 0;
        $totalRating = 0;

        foreach (Star::get() as $star) {
            $selectedCount = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $business->id,
                    "star_id" => $star->id,
                ])
                ->distinct("review_value_news.review_id", "review_value_news.question_id");

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $selectedCount = $selectedCount->whereBetween('review_news.created_at', [
                    $request->start_date,
                    $request->end_date
                ]);
            }

            $selectedCount = $selectedCount->count();

            $totalCount += $selectedCount * $star->value;
            $totalRating += $selectedCount;
        }

        $average_rating = $totalCount > 0 ? $totalCount / $totalRating : 0;

        $timing = $business->times()->with("timeSlots")->where('day', $dayOfWeek)->first();

        $business->average_rating = $average_rating;
        $business->total_rating_count = $totalCount;
        $business->out_of = 5;
        $business->timing = $timing;

        return $business;
    }
}
