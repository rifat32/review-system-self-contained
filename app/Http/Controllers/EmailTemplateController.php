<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailTemplateCreateRequest;
use App\Http\Requests\EmailTemplateUpdateRequest;
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
     *      summary="This method is to store email template",
     *      description="This method is to store email template",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         description="use {{dynamic-username}} {{dynamic-verify-link}} in the template.",
     *         @OA\JsonContent(
     *            required={"type","template","is_active"},
     *    @OA\Property(property="type", type="string", format="string",example="email_verification_mail"),
     *    @OA\Property(property="template", type="string", format="string",example="html template goes here"),
     *    @OA\Property(property="is_active", type="boolean", format="boolean",example="1"),
     *
     *         ),
     *      ),
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
     *          description="Unprocesseble Content",
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

    public function createEmailTemplate(EmailTemplateCreateRequest $request)
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


                return response($template, 201);
            });
        } catch (Exception $e) {

            return $e->getMessage();
        }
    }
    /**
     *
     * @OA\Put(
     *      path="/v1.0/email-templates",
     *      operationId="updateEmailTemplate",
     *      tags={"template_management.email"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update email template",
     *      description="This method is to update email template",
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
     *          description="Unprocesseble Content",
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

    public function updateEmailTemplate(EmailTemplateUpdateRequest $request)
    {
        try {

            return    DB::transaction(function () use (&$request) {

                $request_payload = $request->validated();

                $template  =  tap(EmailTemplate::where(["id" => $request_payload["id"]]))->update(
                    collect($request_payload)->only([
                        "template",

                    ])->toArray()
                )


                    ->first();

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
                return response($template, 201);
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
     *         description="perPage",
     *         required=false,
     *  example="6"
     *      ),
     *              @OA\Parameter(
     *         name="search_key",
     *         in="query",
     *         description="search_key",
     *         required=false,
     *  example="email_verification"
     *      ),
     *              @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=false,
     *  example="2023-01-01"
     *      ),
     *              @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=false,
     *  example="2023-12-31"
     *      ),
     *      summary="This method is to get email templates",
     *      description="This method is to get email templates",
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
     *          description="Unprocesseble Content",
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

            return response()->json($templates, 200);
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
     *      summary="This method is to get email template types ",
     *      description="This method is to get email template types",
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
     *          description="Unprocesseble Content",
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

    public function getEmailTemplateTypes(Request $request)
    {
        try {


            $types = ["email_verification_mail", "forget_password_mail", "welcome_message"];


            return response()->json($types, 200);
        } catch (Exception $e) {

            return $e->getMessage();
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
     *      summary="This method is to delete email templates by ids",
     *      description="This method is to delete email templates by ids",
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
     *          description="Unprocesseble Content",
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

    public function deleteEmailTemplateById($ids, Request $request)
    {
        try {
            $idsArray = explode(',', $ids);
            $idsArray = array_map('intval', $idsArray);

            $existingIds = EmailTemplate::whereIn('id', $idsArray)->pluck('id')->toArray();

            $nonExistingIds = array_diff($idsArray, $existingIds);

            if (!empty($nonExistingIds)) {
                return response()->json([
                    "message" => "Some email templates were not found",
                    "non_existing_ids" => array_values($nonExistingIds)
                ], 404);
            }

            EmailTemplate::whereIn('id', $idsArray)->delete();

            return response()->json([
                "ok" => true,
                "message" => "Email templates deleted successfully",
                "deleted_count" => count($existingIds)
            ], 200);
        } catch (Exception $e) {
            return $e->getMessage();
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
     *      summary="This method is to get email template by id",
     *      description="This method is to get email template by id",
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
     *          description="Unprocesseble Content",
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

    public function getEmailTemplateById($id, Request $request)
    {
        try {



            $template = EmailTemplate::where([
                "id" => $id
            ])
                ->first();
            if (!$template) {
                return response()->json([
                    "message" => "no data found"
                ], 404);
            }
            return response()->json($template, 200);
        } catch (Exception $e) {
        }
    }
}
