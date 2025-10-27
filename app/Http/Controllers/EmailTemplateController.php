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


                $insertableData = $request->validated();
                $template =  EmailTemplate::create($insertableData);



//  if the template is active then other templates of this type will deactive
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

                $updatableData = $request->validated();

                $template  =  tap(EmailTemplate::where(["id" => $updatableData["id"]]))->update(
                    collect($updatableData)->only([
                        "template",

                    ])->toArray()
                )


                    ->first();

         //    if the template is active then other templates of this type will deactive
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
     *      path="/v1.0/email-templates/{perPage}",
     *      operationId="getEmailTemplates",
     *      tags={"template_management.email"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *              @OA\Parameter(
     *         name="perPage",
     *         in="path",
     *         description="perPage",
     *         required=true,
     *  example="6"
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

    public function getEmailTemplates($perPage, Request $request)
    {
        try {




            $templateQuery = new EmailTemplate();

            if (!empty($request->search_key)) {
                $templateQuery = $templateQuery->where(function ($query) use ($request) {
                    $term = $request->search_key;
                    $query->where("type", "like", "%" . $term . "%");
                });
            }

            if (!empty($request->start_date) && !empty($request->end_date)) {
                $templateQuery = $templateQuery->whereBetween('created_at', [
                    $request->start_date,
                    $request->end_date
                ]);
            }

            $templates = $templateQuery->orderByDesc("id")->paginate($perPage);
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

    public function getEmailTemplateTypes( Request $request)
    {
        try {


$types = ["email_verification_mail","forget_password_mail","welcome_message"];


            return response()->json($types, 200);
        } catch (Exception $e) {

            return $e->getMessage();
        }
    }

     /**
        *
     *     @OA\Delete(
     *      path="/v1.0/email-templates/{id}",
     *      operationId="deleteEmailTemplateById",
     *      tags={"z.unused"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to delete email template by id",
     *      description="This method is to delete email template by id",
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

    public function deleteEmailTemplateById($id,Request $request) {

        try{

           EmailTemplate::where([
            "id" => $id
           ])
           ->delete();

            return response()->json(["ok" => true], 200);
        } catch(Exception $e){

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
            if(!$template){
                return response()->json([
                     "message" => "no data found"
                ], 404);
            }
            return response()->json($template, 200);
        } catch (Exception $e) {


        }
    }
}
