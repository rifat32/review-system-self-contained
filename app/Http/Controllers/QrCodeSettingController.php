<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\QrCodeSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use OpenApi\Annotations as OA;

class QrCodeSettingController extends Controller
{
    /**
     * @OA\Get(
     *      path="/v1.0/qr-code-settings/{businessId}",
     *      operationId="getQRCodeSettings",
     *      tags={"business_management.qr_code_settings"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get QR Code Settings",
     *      description="Retrieve QR code settings for a specific business",
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          description="ID of the business",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="QR Code Settings retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="slug", type="string", example="preview-qr"),
     *                  @OA\Property(property="qrStyling", type="object",
     *                      @OA\Property(property="dotsOptions", type="object",
     *                          @OA\Property(property="type", type="string", example="rounded"),
     *                          @OA\Property(property="color", type="string", example="#0d7ff2")
     *                      ),
     *                      @OA\Property(property="cornersSquareOptions", type="object",
     *                          @OA\Property(property="type", type="string", example="extra-rounded"),
     *                          @OA\Property(property="color", type="string", example="#0d7ff2")
     *                      ),
     *                      @OA\Property(property="cornersDotOptions", type="object",
     *                          @OA\Property(property="type", type="string", example="dot"),
     *                          @OA\Property(property="color", type="string", example="#0d7ff2")
     *                      ),
     *                      @OA\Property(property="backgroundOptions", type="object",
     *                          @OA\Property(property="color", type="string", example="#ffffff"),
     *                          @OA\Property(property="margin", type="integer", example=10)
     *                      ),
     *                      @OA\Property(property="image", type="string", example=""),
     *                      @OA\Property(property="imageOptions", type="object",
     *                          @OA\Property(property="hideBackgroundDots", type="boolean", example=true),
     *                          @OA\Property(property="imageSize", type="number", format="float", example=0.4),
     *                          @OA\Property(property="margin", type="integer", example=10)
     *                      )
     *                  ),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=404, description="Business not found")
     * )
     */
    public function getQRCodeSettings($businessId)
    {
        $authUser= auth()->user();

        if(!$authUser->hasRole('business_owner')){
            throw new AccessDeniedHttpException('You can not perform this action');
        }

        $business = Business::findOrFail($businessId);


        $settings = $business->qrCodeSettings;

        if (!$settings) {
            // Return default structure if not found, or create one
            return response()->json([
                'slug' => 'preview-qr',
                'qrStyling' => [
                   'dotsOptions' => [
                        'type' => 'rounded',
                        'color' => '#0d7ff2',
                    ],
                    'cornersSquareOptions' => [
                        'type' => 'extra-rounded',
                        'color' => '#0d7ff2',
                    ],
                    'cornersDotOptions' => [
                        'type' => 'dot',
                        'color' => '#0d7ff2',
                    ],
                    'backgroundOptions' => [
                        'color' => '#ffffff',
                        'margin' => 10,
                    ],
                    'image' => '',
                    'imageOptions' => [
                        'hideBackgroundDots' => true,
                        'imageSize' => 0.4,
                        'margin' => 10,
                    ],
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR Code Settings retrieved successfully',
            'data' => $settings
        ],200);
    }

    /**
     * @OA\Put(
     *      path="/v1.0/qr-code-settings",
     *      operationId="updateQRCodeSettings",
     *      tags={"business_management.qr_code_settings"},
     *      security={{"bearerAuth": {}}},
     *      summary="Update QR Code Settings",
     *      description="Update QR code settings for a business",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"business_id", "slug", "qrStyling"},
     *              @OA\Property(property="business_id", type="integer", example=1),
     *              @OA\Property(property="slug", type="string", example="preview-qr"),
     *              @OA\Property(property="qrStyling", type="object",
     *                  @OA\Property(property="dotsOptions", type="object",
     *                      @OA\Property(property="type", type="string", example="rounded"),
     *                      @OA\Property(property="color", type="string", example="#0d7ff2")
     *                  ),
     *                  @OA\Property(property="cornersSquareOptions", type="object",
     *                      @OA\Property(property="type", type="string", example="extra-rounded"),
     *                      @OA\Property(property="color", type="string", example="#0d7ff2")
     *                  ),
     *                  @OA\Property(property="cornersDotOptions", type="object",
     *                      @OA\Property(property="type", type="string", example="dot"),
     *                      @OA\Property(property="color", type="string", example="#0d7ff2")
     *                  ),
     *                  @OA\Property(property="backgroundOptions", type="object",
     *                      @OA\Property(property="color", type="string", example="#ffffff"),
     *                      @OA\Property(property="margin", type="integer", example=10)
     *                  ),
     *                  @OA\Property(property="image", type="string", example=""),
     *                  @OA\Property(property="imageOptions", type="object",
     *                      @OA\Property(property="hideBackgroundDots", type="boolean", example=true),
     *                      @OA\Property(property="imageSize", type="number", format="float", example=0.4),
     *                      @OA\Property(property="margin", type="integer", example=10)
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="QR Code Settings updated successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateQRCodeSettings(Request $request)
    {
        $request_payload = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'slug' => 'required|string',
            'qrStyling' => 'required|array',
        ]);


        $businessId = $request_payload['business_id'];

        // Authorization check
        // if (auth()->user()->cant('update', Business::find($businessId))) { ... }

        $settings = QrCodeSetting::updateOrCreate(
            ['business_id' => $businessId],
            [
                'slug' => $request_payload['slug'],
                'qrStyling' => $request_payload['qrStyling'],
            ]
        );

        return response()->json([
            'success'=> true,
            'message' => 'QR Code Settings updated successfully',
            'data' => $settings
        ], 200);
    }
}
