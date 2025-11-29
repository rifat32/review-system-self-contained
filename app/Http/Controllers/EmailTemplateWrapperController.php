<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplateWrapper;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailTemplateWrapperController extends Controller
{
    /**
     *
     * @OA\Put(
     *      path="/v1.0/email-template-wrappers/{id}",
     *      operationId="updateEmailTemplateWrapper",
     *      tags={"template_management.wrapper.email"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="This method is to update Email template wrapper",
     *      description="This method is to update Email template wrapper",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="use [content] in the template",
     *          @OA\JsonContent(
     *              required={"id","template","is_active"},
     *              @OA\Property(property="id", type="number", format="number", example="1"),
     *              @OA\Property(property="name", type="string", format="string", example="email v1"),
     *              @OA\Property(property="is_active", type="number", format="number", example="1"),
     *              @OA\Property(property="template", type="string", format="string", example="html template goes here")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Email template wrapper updated successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation errors")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Forbidden")
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Bad Request")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Email template wrapper not found")
     *          )
     *      )
     * )
     */


    public function updateEmailTemplateWrapper(Request $request, int $id)
    {
        try {
            $updatableData = $request->toArray();

            $template = DB::transaction(function () use ($updatableData, $id) {
                $template = EmailTemplateWrapper::find($id);

                if (!$template) {
                    throw new Exception("Email template wrapper not found", 404);
                }

                $template->update($updatableData);

                // If the template is active, deactivate other templates of the same type
                if ($template->is_active) {
                    EmailTemplateWrapper::where("id", "!=", $template->id)
                        ->where("type", $template->type)
                        ->update(["is_active" => false]);
                }

                return $template;
            });

            return response()->json([
                "success" => true,
                "message" => "Email template wrapper updated successfully",
                "data" => $template
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * @OA\Get(
     *     path="/v1.0/email-template-wrappers",
     *     operationId="getEmailTemplateWrappers",
     *     tags={"template_management.wrapper.email"},
     *     security={{"bearerAuth": {}}},
     *     summary="This method is to get email template wrappers",
     *     description="This method is to get email template wrappers",
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="perPage",
     *         required=false,
     *         @OA\Schema(type="integer", example=6)
     *     ),
     *     @OA\Parameter(
     *         name="search_key",
     *         in="query",
     *         description="search_key",
     *         required=false,
     *         @OA\Schema(type="string", example="email_verification")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2023-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2023-12-31")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Content",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *         @OA\JsonContent()
     *     )
     * )
     */


    public function getEmailTemplateWrappers(Request $request)
    {
        try {
            $templateQuery = EmailTemplateWrapper::filter();


            $templates = retrieve_data($templateQuery);

            return response()->json([
                "success" => true,
                "message" => "Email template wrappers retrieved successfully",
                "meta" => $templates['meta'],
                "data" => $templates['data']
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * @OA\Get(
     *     path="/v1.0/email-template-wrappers/{id}",
     *     operationId="getEmailTemplateWrapperById",
     *     tags={"template_management.wrapper.email"},
     *     security={{"bearerAuth": {}}},
     *     summary="This method is to get email template wrapper by id",
     *     description="This method is to get email template wrapper by id",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Email template wrapper ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=6)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Email template wrapper retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Content",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *         @OA\JsonContent()
     *     )
     * )
     */


    public function getEmailTemplateWrapperById($id)
    {
        try {
            $template = EmailTemplateWrapper::find($id);


            if (!$template) {
                return response()->json([
                    "success" => false,
                    "message" => "Email template wrapper not found"
                ], 404);
            }

            // Return success response
            return response()->json([
                "success" => true,
                "message" => "Email template wrapper retrieved successfully",
                "data" => $template
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
