<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SurveyQuestion;

class SurveyQuestionController extends Controller
{
    /**
     * UPDATE THE DISPLAY ORDER FOR MULTIPLE SURVEY QUESTIONS.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
}
