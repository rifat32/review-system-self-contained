<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Survey;
use Illuminate\Http\Request;
use App\Models\SurveyQuestion;

class SurveyQuestionController extends Controller
{
    /**
     * @OA\Patch(
     *      path="/v1.0/survey-questions/display-order",
     *      operationId="surveyQuestionDisplayOrder",
     *      tags={"survey_question_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Update display order for multiple survey questions",
     *      description="Update the order_no for multiple survey questions based on survey_id and question_id",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"updates"},
     *            @OA\Property(
     *                property="updates",
     *                type="array",
     *                @OA\Items(
     *                    type="object",
     *                    required={"survey_id","question_id","order_no"},
     *                    @OA\Property(property="survey_id", type="integer", example=1),
     *                    @OA\Property(property="question_id", type="integer", example=5),
     *                    @OA\Property(property="order_no", type="integer", example=2)
     *                )
     *            )
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Not Found",
     *   @OA\JsonContent()
     * )
     *     )
     */
    public function surveyQuestionDisplayOrder(Request $request)
    {
        // VALIDATE THE REQUEST DATA
        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.survey_id' => 'required|exists:surveys,id',
            'updates.*.question_id' => 'required|exists:questions,id',
            'updates.*.order_no' => 'required|integer|min:1',
        ]);

        $updated = [];
        // LOOP THROUGH EACH UPDATE
        foreach ($request->updates as $update) {
            // FIND THE SURVEY QUESTION RECORD
            $surveyQuestion = SurveyQuestion::where('survey_id', $update['survey_id'])
                ->where('question_id', $update['question_id'])
                ->first();

            if ($surveyQuestion) {
                // UPDATE THE ORDER NUMBER
                $surveyQuestion->update(['order_no' => $update['order_no']]);
                // ADD TO UPDATED ARRAY
                $updated[] = $surveyQuestion;
            }
        }

        // RETURN SUCCESS RESPONSE WITH UPDATED DATA
        return response()->json([
            'success' => true,
            'message' => 'Display orders updated successfully',
            'data' => $updated
        ]);
    }

    /**
     * GET SURVEY QUESTIONS BY SURVEY IDS AND QUESTION IDS.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Get(
     *      path="/v1.0/survey-questions",
     *      operationId="getSurveyQuestionsBySurveyAndQuestionIds",
     *      tags={"survey_question_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Get survey questions by survey IDs and question IDs",
     *      description="Retrieve survey questions data filtered by survey IDs and question IDs (comma-separated)",
     *
     *      @OA\Parameter(
     *          name="survey_ids",
     *          in="query",
     *          required=false,
     *          description="Comma-separated survey IDs (e.g., 1,2,3)",
     *          @OA\Schema(type="string", example="1,2,3")
     *      ),
     *      @OA\Parameter(
     *          name="question_ids",
     *          in="query",
     *          required=false,
     *          description="Comma-separated question IDs (e.g., 4,5,6)",
     *          @OA\Schema(type="string", example="4,5,6")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SurveyQuestion"))
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *          @OA\JsonContent()
     *      )
     *     )
     */
    public function getBySurveyAndQuestionIds(Request $request)
    {
        // GET BUSINESS ID FROM AUTHENTICATED USER
        $businessId = $request->user()->business_id;

        // PARSE COMMA-SEPARATED IDS INTO ARRAYS
        $surveyIds = $request->has('survey_ids') && $request->input('survey_ids')
            ? array_map('intval', array_filter(explode(',', $request->input('survey_ids'))))
            : [];

        $questionIds = $request->has('question_ids') && $request->input('question_ids')
            ? array_map('intval', array_filter(explode(',', $request->input('question_ids'))))
            : [];

        // CHECK IF AT LEAST ONE FILTER IS PROVIDED
        if (empty($surveyIds) && empty($questionIds)) {
            return response()->json([
                'success' => false,
                'message' => 'At least one of survey_ids or question_ids is required'
            ], 422);
        }

        // VALIDATE SURVEY IDS EXIST IN DATABASE AND BELONG TO USER'S BUSINESS
        if (!empty($surveyIds)) {
            $existingSurveyIds = Survey::whereIn('id', $surveyIds)
                ->where('business_id', $businessId)
                ->pluck('id')
                ->toArray();
            $invalidSurveyIds = array_diff($surveyIds, $existingSurveyIds);

            if (!empty($invalidSurveyIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or unauthorized survey IDs: ' . implode(', ', $invalidSurveyIds),
                    'data' => [
                        'existingSurveyIds' => $existingSurveyIds,
                        'invalidSurveyIds' => array_values($invalidSurveyIds),
                    ]
                ], 422);
            }
        }

        // VALIDATE QUESTION IDS EXIST IN DATABASE AND BELONG TO USER'S BUSINESS OR ARE DEFAULT
        if (!empty($questionIds)) {
            $existingQuestionIds = Question::whereIn('id', $questionIds)
                ->where(function ($query) use ($businessId) {
                    $query->where('business_id', $businessId)
                        ->orWhereNull('business_id');
                })
                ->pluck('id')
                ->toArray();
            $invalidQuestionIds = array_diff($questionIds, $existingQuestionIds);

            if (!empty($invalidQuestionIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or unauthorized question IDs: ' . implode(', ', $invalidQuestionIds),
                    'data' => [
                        'existingQuestionIds' => $existingQuestionIds,
                        'invalidQuestionIds' => array_values($invalidQuestionIds),
                    ]
                ], 422);
            }
        }

        // BUILD QUERY
        $query = SurveyQuestion::with(['question']);

        // FILTER BY SURVEY IDS IF PROVIDED
        if (!empty($surveyIds)) {
            $query->whereIn('survey_id', $surveyIds);
        }

        // FILTER BY QUESTION IDS IF PROVIDED
        if (!empty($questionIds)) {
            $query->whereIn('question_id', $questionIds);
        }

        // FETCH SURVEY QUESTIONS WITH RELATED DATA
        $surveyQuestions = $query->orderBy('order_no')->get();

        // RETURN SUCCESS RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Survey questions retrieved successfully',
            'data' => $surveyQuestions
        ]);
    }
}
