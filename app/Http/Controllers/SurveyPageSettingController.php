<?php

namespace App\Http\Controllers;

use App\Models\SurveyPageSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SurveyPageSettingController extends Controller
{
    /**
     * @OA\Get(
     *      path="/v1.0/survey-page-settings/{businessId}",
     *      operationId="getSurveySettingsByBusinessId",
     *      tags={"business_management.settings"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get survey page settings by business ID",
     *      description="Retrieve survey page settings for a specific business.",
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Settings retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Survey settings retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Settings not found"
     *      )
     * )
     */
    public function getSurveySettingsByBusinessId($businessId)
    {
        $settings = SurveyPageSetting::where('business_id', $businessId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Survey settings retrieved successfully',
            'data' => $settings
        ]);
    }

    /**
     * @OA\Put(
     *      path="/v1.0/survey-page-settings",
     *      operationId="updateSurveySettings",
     *      tags={"business_management.settings"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Update survey page settings",
     *      description="Update or create survey page settings for a business. Only owners can perform this action.",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"business_id"},
     *              @OA\Property(property="business_id", type="integer", example=1),
     *              @OA\Property(property="background_color", type="string", example="#0A4B67"),
     *              @OA\Property(property="overall_heading", type="string", example="Your Overall Experience"),
     *              @OA\Property(property="survey_heading", type="string", example="How would you rate us?"),
     *              @OA\Property(property="heading_color", type="string", example="#0A4B67"),
     *              @OA\Property(property="sub_heading", type="string", example="Please help us improve our services"),
     *              @OA\Property(property="sub_heading_color", type="string", example="#E8EEFF")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Settings updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Survey settings updated successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error"
     *      )
     * )
     */
    public function updateSurveySettings(Request $request)
    {

        $authUser= auth()->user();

        if(!$authUser->hasRole('business_owner')){
            throw new AccessDeniedHttpException('You are not authorized to perform this action');
        };


        $payload_data = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'background_color' => 'nullable|string',
            'overall_heading' => 'nullable|string',
            'survey_heading' => 'nullable|string',
            'heading_color' => 'nullable|string',
            'sub_heading' => 'nullable|string',
            'sub_heading_color' => 'nullable|string',
            'question_text_color' => 'nullable|string',
            'question_background_color' => 'nullable|string',
            'tag_text_color' => 'nullable|string',
            'tag_background_color' => 'nullable|string',
            'tag_active_text_color' => 'nullable|string',
            'tag_active_background_color' => 'nullable|string',
            'service_text_color' => 'nullable|string',
            'service_background_color' => 'nullable|string',
            'service_area_text_color' => 'nullable|string',
            'service_area_background_color' => 'nullable|string',
            'active_service_area_text_color' => 'nullable|string',
            'active_service_area_background_color' => 'nullable|string',
            'staff_heading' => 'nullable|string',
            'staff_heading_color' => 'nullable|string',
            'staff_background_color' => 'nullable|string',
            'staff_card_background_color' => 'nullable|string',
            'staff_name_color' => 'nullable|string',
            'staff_role_color' => 'nullable|string',
            'staff_active_background_color' => 'nullable|string',
            'staff_active_border_color' => 'nullable|string',
            'remarks_button_text' => 'nullable|string',
            'remarks_button_text_color' => 'nullable|string',
            'remarks_button_background_color' => 'nullable|string',
            'remarks_text' => 'nullable|string',
            'remarks_text_color' => 'nullable|string',
            'remarks_background_color' => 'nullable|string',
            'field_background_color' => 'nullable|string',
            'field_text_color' => 'nullable|string',
            'details_heading' => 'nullable|string',
            'details_heading_color' => 'nullable|string',
            'details_background_color' => 'nullable|string',
            'details_label_color' => 'nullable|string',
            'actions_background_color' => 'nullable|string',
            'actions_buttons_text_color' => 'nullable|string',
            'actions_button_background_color' => 'nullable|string',
        ]);


        $settings = SurveyPageSetting::updateOrCreate(
            ['business_id' => $request->business_id],
            $payload_data
        );

        return response()->json([
            'success' => true,
            'message' => 'Survey settings updated successfully',
            'data' => $settings
        ]);
    }
}
