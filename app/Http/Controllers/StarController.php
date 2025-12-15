<?php

namespace App\Http\Controllers;

use App\Models\Star;
use Illuminate\Http\Request;

class StarController extends Controller
{


    // ##################################################
    // This method is to store star
    // ##################################################
    public function createStar(Request $request)
    {
        return "this api is closed by the developer";


        $star = [
            'value' => $request->value,
            // 'business_id' => $request->business_id
        ];
        if ($request->user()->hasRole("superadmin")) {
            $star["is_default"] = true;
        } else {
            $star["is_default"] = false;
        }

        $createdStar =    Star::create($star);


        // RETURN
        return response()->json([
            "status" => true,
            "message" => "Star created successfully",
            "data" => $createdStar
        ], 201);
    }

    // ##################################################
    // This method is to get star
    // ##################################################
    public function getAllStar(Request $request)
    {

        $query =  Star::query();

        // IF SUPER ADMIN
        if ($request->user()->hasRole("superadmin")) {
            $query =  $query->where(["is_default" => true]);
        }

        // ELSE BUSINESS USER
        $business_id = $request->user()->business->id;

        if (!$business_id) {
            return response()->json([
                "success" => false,
                "message" => "No Business Found"
            ], 404);
        }


        $query =  $query
            ->where("business_id", $business_id)
            ->where(["is_default" => false]);

        $questions =  retrieve_data($query);

        return response()->json([
            "status" => true,
            "message" => "Stars fetched successfully",
            "meta" => $questions['meta'],
            "data" => $questions['data'],
        ], 200);
    }


    // ##################################################
    // This method is to get star by id
    // ##################################################
    public function   getStarById($id, Request $request)
    {
        $star =  Star::find($id);

        if (!$star) {
            return response()->json([
                "status" => false,
                "message" => "Star not found"
            ], 404);
        }


        return response()->json([
            "status" => true,
            "message" => "Star fetched successfully",
            "data" => $star
        ], 200);
    }


    // ##################################################
    // This method is to update star
    // ##################################################
    public function updateStar(Request $request)
    {
        return "this api is closed by the developer";
        $question = [
            'value' => $request->value
        ];


        $checkStar =    Star::find($request->id);

        if (!$checkStar) {
            return response()->json([
                "message" => "Star not found"
            ], 404);
        }

        if ($checkStar->is_default == true && !$request->user()->hasRole("superadmin")) {
            return response()->json([
                "success" => false,
                "message" => "you can not update the question. only superadmin can update the default question"
            ], 403);
        }
        $checkStar->update($question);


        return response()->json([
            "status" => true,
            "message" => "Star updated successfully",
            "data" => $checkStar
        ], 200);
    }

    public function   deleteStarById($id, Request $request)
    {
        return "this api is closed by the developer";


        $star = Star::find($id);

        if (!$star) {
            return response([
                "success" => false,
                "message" => "Star not found"
            ], 400);
        }

        // 
        return response([
            "success" => true,
            "message" => "Star deleted successfully"
        ], 200);
    }
}
