<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailTemplateRequest;
use App\Models\EmailTemplate;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailTemplateController extends Controller
{




    /**
     *
     * @OA\Post(
     *      path="/v1.0/email-templates",
     *      operationId="createEmailTemplate",
     *      tags={"z.unused"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store Email template",
     *      description="This method is to store Email template",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         description="Use {{dynamic-username}} {{dynamic-verify-link}} in the template.",
     *         @OA\JsonContent(
     *            required={"type","template","is_active"},
     *    @OA\Property(property="type", type="string", format="string",example="email_verification_mail"),
     *    @OA\Property(property="template", type="string", format="string",example="html template goes here"),
     *    @OA\Property(property="is_active", type="boolean", format="boolean",example="1"),
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Email template created successfully"),
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
     *              @OA\Property(property="message", type="string", example="Not found")
     *          )
     *      )
     *     )
     */

    public function createEmailTemplate(EmailTemplateRequest $request)
    {
        try {
            return    DB::transaction(function () use (&$request) {

                $request_payload = $request->validated();
                $template =  EmailTemplate::create($request_payload);

                //  if the template is active then other templates of this type will
                if ($template->is_active) {
                    EmailTemplate::where("id", "!=", $template->id)
                        ->where([
                            "type" => $template->type
                        ])
                        ->update([
                            "is_active" => false
                        ]);
                }

                // return response
                return response([
                    "success" => true,
                    "message" => "Email template created successfully",
                    "data" => $template
                ], 201);
            });
        } catch (Exception $e) {

            throw $e;
        }
    }
    /**
     *
     * @OA\Put(
     *      path="/v1.0/email-templates/{id}",
     *      operationId="updateEmailTemplate",
     *      tags={"template_management.email"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update Email template",
     *      description="This method is to update Email template",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","template","is_active"},
     *    @OA\Property(property="id", type="number", format="number", example="1"),
     *    @OA\Property(property="template", type="string", format="string",example="html template goes here"),
     *    @OA\Property(property="is_active", type="boolean", format="boolean",example="1"),
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Email template updated successfully"),
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
     *              @OA\Property(property="message", type="string", example="Email template not found")
     *          )
     *      )
     *     )
     */

    public function updateEmailTemplate(EmailTemplateRequest $request, int $id)
    {
        try {

            return    DB::transaction(function () use (&$request, $id) {
                $request_payload = $request->validated();

                $template  =  EmailTemplate::find($id);

                if (!$template) {
                    return response()->json([
                        "success" => false,
                        "message" => "Email template not found"
                    ], 404);
                }

                $template->update($request_payload);

                //    if the template is active then other templates of this type will
                if ($template->is_active) {
                    EmailTemplate::where("id", "!=", $template->id)
                        ->where([
                            "type" => $template->type
                        ])
                        ->update([
                            "is_active" => false
                        ]);
                }
                return response([
                    "success" => true,
                    "message" => "Email template updated successfully",
                    "data" => $template
                ], 201);
            });
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    /**
     *
     * @OA\Get(
     *      path="/v1.0/email-templates",
     *      operationId="getEmailTemplates",
     *      tags={"template_management.email"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *              @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Per page",
     *         required=false,
     *  example="6"
     *      ),
     *              @OA\Parameter(
     *         name="search_key",
     *         in="query",
     *         description="Search key",
     *         required=false,
     *  example="email_verification"
     *      ),
     *              @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date",
     *         required=false,
     *  example="2023-01-01"
     *      ),
     *              @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date",
     *         required=false,
     *  example="2023-12-31"
     *      ),
     *      summary="This method is to get Email templates",
     *      description="This method is to get Email templates",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Email templates retrieved successfully"),
     *              @OA\Property(property="data", type="array", @OA\Items(type="object"))
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
     *              @OA\Property(property="message", type="string", example="Not found")
     *          )
     *      )
     *     )
     */

    public function getEmailTemplates(Request $request)
    {
        try {
            $templateQuery = EmailTemplate::filter()
                ->orderByDesc("id");

            if ($request->has('perPage')) {
                $templates = $templateQuery->paginate($request->perPage);
            } else {
                $templates = $templateQuery->get();
            }

            return response()->json([
                "success" => true,
                "message" => "Email templates retrieved successfully",
                "data" => $templates
            ], 200);
        } catch (Exception $e) {

            return $e->getMessage();
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/email-template-types",
     *      operationId="getEmailTemplateTypes",
     *      tags={"z.unused"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *
     *      summary="This method is to get Email template types",
     *      description="This method is to get Email template types",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getEmailTemplateTypes()
    {
        try {

            // define types
            $types = ["email_verification_mail", "forget_password_mail", "welcome_message"];

            // return response
            return response()->json([
                "success" => true,
                "message" => "Email template types retrieved successfully",
                "data" => $types
            ], 200);
        } catch (Exception $e) {

            throw $e;
        }
    }

    /**
     *
     *     @OA\Delete(
     *      path="/v1.0/email-templates/{ids}",
     *      operationId="deleteEmailTemplateById",
     *      tags={"z.unused"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="ids",
     *         in="path",
     *         description="ids",
     *         required=true,
     *  example="1,2,3"
     *      ),
     *      summary="This method is to delete Email templates by ids",
     *      description="This method is to delete Email templates by ids",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function deleteEmailTemplateById($ids)
    {
        try {
            // convert ids string to array
            $idsArray = explode(',', $ids);
            $idsArray = array_map('intval', $idsArray);

            // find existing ids
            $existingIds = EmailTemplate::whereIn('id', $idsArray)->pluck('id')->toArray();

            // find non existing ids
            $nonExistingIds = array_diff($idsArray, $existingIds);

            // if non existing ids
            if (!empty($nonExistingIds)) {
                return response()->json([
                    "success" => false,
                    "message" => "Some email templates were not found",
                    "non_existing_ids" => array_values($nonExistingIds)
                ], 404);
            }

            // delete email templates
            EmailTemplate::whereIn('id', $idsArray)->delete();

            // return response
            return response()->json([
                "success" => true,
                "message" => "Email templates deleted successfully",
                "deleted_count" => count($existingIds)
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/email-templates/single/{id}",
     *      operationId="getEmailTemplateById",
     *      tags={"template_management.email"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="6"
     *      ),
     *      summary="This method is to get Email template by id",
     *      description="This method is to get Email template by id",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getEmailTemplateById($id)
    {
        try {
            $template = EmailTemplate::find($id);

            // check if template exists
            if (!$template) {
                return response()->json([
                    "success" => false,
                    "message" => "no data found"
                ], 404);
            }

            // return response
            return response()->json([
                "success" => true,
                "message" => "Email template retrieved successfully",
                "data" => $template
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
