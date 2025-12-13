<?php

namespace App\Http\Controllers;

use App\Models\StarTagQuestion;
use Illuminate\Http\Request;

class StarTagQuestionController extends Controller
{
    // ##################################################
    // This method is to store star tag
    // ##################################################
    public function createStarTagQuestion(Request $request)
    {
        $payload = $request->validate([
            'question_id' => 'required|integer|exists:questions,id',
            'tag_id' => 'required|integer|exists:tags,id',
            'star_id' => 'required|integer|exists:stars,id',
        ]);

        // Set is_default to true only if the user is a superadmin
        if ($request->user()->hasRole("superadmin")) {
            $payload["is_default"] = true;
        }

        // Create the question
        $createdQuestion =    StarTagQuestion::create($payload);

        return response()->json([
            "success" => true,
            "message" => "Star Tag Question created successfully",
            "data" => $createdQuestion
        ], 201);
    }



    // ##################################################
    // This method is to get star tag by id
    // ##################################################
    public function   getStarTagQuestionById($id, Request $request)
    {

        $questions =    StarTagQuestion::where(["id" => $id])
            ->with("question", "star", "tag")
            ->first();

        if (!$questions) {
            return response()->json([
                "success" => false,
                "message" => "Star Tag Question not found"
            ], 404);
        }

        // send response
        return response()->json([
            "success" => true,
            "message" => "Star Tag Question fetched successfully",
            "data" => $questions
        ], 200);
    }


    // ##################################################
    // This method is to get star tag
    // ##################################################
    public function   getAllStarQuestionTag(Request $request)
    {
        $query =  StarTagQuestion::where(["question_id" => $request->question_id])
            ->with("question", "star", "tag");

        // IF SUPER ADMIN
        if ($request->user()->hasRole("superadmin")) {
            $query->where(["is_default" => true]);
        }

        $query
            ->where("is_default", false)
            ->where("business_id", $request->user()->business()->value('id'));

        // ELSE BUSINESS USER
        $questions =  $query->get();


        return response()->json([
            "success" => true,
            "message" => "Star Tag Question fetched successfully",
            "data" => $questions
        ], 200);
    }

    // ##################################################
    // This method is to update star tag
    // ##################################################
    public function updateStarTagQuestion(Request $request, $id)
    {
        $payload = $request->validate([
            'question_id' => 'required|integer|exists:questions,id',
            'tag_id' => 'required|integer|exists:tags,id',
            'star_id' => 'required|integer|exists:stars,id',
        ]);

        // Set is_default to true only if the user is a superadmin
        $checkQuestion =    StarTagQuestion::where(["id" => $id])->first();

        if ($checkQuestion->is_default == true && !$request->user()->hasRole("superadmin")) {
            return response()->json([
                "success" => false,
                "message" => "you can not update the question."
            ], 403);
        }


        $updatedQuestion =  $checkQuestion->update($payload);

        return response()->json([
            "success" => true,
            "message" => "Star Tag Question updated successfully",
            "data" => $updatedQuestion
        ], 200);
    }

    // ##################################################
    // This method is to delete star tag question by id
    // ##################################################
    public function   deleteStarTagQuestionById($id, Request $request)
    {
        $starTagQuestion = StarTagQuestion::find($id);

        if (!$starTagQuestion) {
            return response()->json([
                "success" => false,
                "message" => "Star Tag Question not found"
            ], 404);
        }


        // delete
        $starTagQuestion->delete();


        return response()->json([
            "success" => true,
            "message" => "Star Tag Question deleted successfully"
        ], 200);
    }
}
