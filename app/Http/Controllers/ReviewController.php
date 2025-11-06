<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Review;
use App\Models\ReviewValue;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
  
    public function store($businessId, $rate, Request $request)
    {

        ReviewValue::where([
            "business_id" => $businessId,
            "rate" => $rate
        ])
            ->delete();

        $reviewValues = $request->reviewvalue;
        $raviewValue_array = [];
        foreach ($reviewValues as $reviewValue) {
            $reviewValue["business_id"] = $businessId;
            $reviewValue["rate"] = $rate;
            $createdReviewValue =  ReviewValue::create($reviewValue);
            array_push($raviewValue_array, $createdReviewValue);
        }

        return response($raviewValue_array, 201);
    }
    // ##################################################
    // This method is to get   ReviewValue
    // ##################################################
    public function getReviewValues($businessId, $rate, Request $request)
    {
        // with
        $reviewValues = ReviewValue::where([
            "business_id" => $businessId,
            "rate" => $rate,

        ])
            ->get();

        return response($reviewValues, 200);
    }
    // ##################################################
    // This method is to get ReviewValue by id
    // ##################################################
    public function getreviewvalueById($businessId, Request $request)
    {
        // with
        $reviewValues = ReviewValue::where([
            "business_id" => $businessId
        ])
            ->first();


        return response($reviewValues, 200);
    }
    // ##################################################
    // This method is to get average
    // ##################################################
    public function  getAverage($businessId, $start, $end, Request $request)
    {
        // with
        $reviews = Review::where([
            "business_id" => $businessId
        ])
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $data["total"]   = $reviews->count();
        $data["one"]   = 0;
        $data["two"]   = 0;
        $data["three"] = 0;
        $data["four"]  = 0;
        $data["five"]  = 0;
        foreach ($reviews as $review) {
            switch ($review->rate) {
                case 1:
                    $data["one"] += 1;
                    break;
                case 2:
                    $data["two"] += 1;
                    break;
                case 3:
                    $data["three"] += 1;
                    break;
                case 4:
                    $data["four"] += 1;
                    break;
                case 5:
                    $data["five"] += 1;
                    break;
            }
        }


        return response($data, 200);
    }
    // ##################################################
    // This method is to store review2
    // ##################################################
    public function store2($businessId, Request $request)
    {

        ReviewValue::where([
            "business_id" => $businessId,
            "rate" => $request->rate
        ])
            ->delete();
        $reviewValue = [
            "tag" => $request->tag,
            "rate" => $request->rate,
            "business_id" => $businessId
        ];

        $createdReviewValue =  ReviewValue::create($reviewValue);



        return response($createdReviewValue, 201);
    }
    // ##################################################
    // This method is to filter   Review
    // ##################################################
    public function  filterReview($businessId, $rate, $start, $end, Request $request)
    {
        // with
        $reviewValues = Review::where([
            "business_id" => $businessId,
            "rate" => $rate
        ])
            ->whereBetween('created_at', [$start, $end])
            ->get();


        return response($reviewValues, 200);
    }
    // ##################################################
    // This method is to get review by business id
    // ##################################################
    public function  getReviewByBusinessId($businessId, Request $request)
    {
        // with
        $reviewValue = Review::where([
            "business_id" => $businessId,
        ])
            ->get();


        return response($reviewValue, 200);
    }
    // ##################################################
    // This method is to get customer review
    // ##################################################
    public function  getCustommerReview($businessId, $start, $end, Request $request)
    {
        // with
        $data["reviews"] = Review::where([
            "business_id" => $businessId,
        ])
            ->whereBetween('created_at', [$start, $end])
            ->get();
        $data["total"]   = $data["reviews"]->count();
        $data["one"]   = 0;
        $data["two"]   = 0;
        $data["three"] = 0;
        $data["four"]  = 0;
        $data["five"]  = 0;
        foreach ($data["reviews"]  as $reviewValue) {
            switch ($reviewValue->rate) {
                case 1:
                    $data["one"] += 1;
                    break;
                case 2:
                    $data["two"] += 1;
                    break;
                case 3:
                    $data["three"] += 1;
                    break;
                case 4:
                    $data["four"] += 1;
                    break;
                case 5:
                    $data["five"] += 1;
                    break;
            }
        }

        return response($data, 200);
    }

    // ##################################################
    // This method is to store review
    // ##################################################
    public function storeReview($businessId,  Request $request)
    {

        $review = [
            'description' => $request->description,
            'business_id' => $businessId,
            'rate' => $request->rate,
            'user_id' => $request->user()->id,
            'comment' => $request->comment,

        ];
        Review::create($review);


        return response($review, 201);
    }
}
