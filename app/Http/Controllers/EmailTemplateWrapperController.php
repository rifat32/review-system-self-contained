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
     *      path="/v1.0/email-template-wrappers",
     *      operationId="updateEmailTemplateWrapper",
     *      tags={"template_management.wrapper.email"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update email template wrapper",
     *      description="This method is to update email template wrapper",
     *
     *  @OA\RequestBody(
     *         required=true,
     *  description="use [content] in the template",
     *         @OA\JsonContent(
     *            required={"id","template","is_active"},
     *    @OA\Property(property="id", type="number", format="number", example="1"),
     *   * *    @OA\Property(property="name", type="string", format="string",example="emal v1"),
     *   * *   * *    @OA\Property(property="is_active", type="number", format="number",example="1"),
     *    @OA\Property(property="template", type="string", format="string",example="html template goes here"),

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

    public function updateEmailTemplateWrapper(Request $request)
    {
        try {

            return    DB::transaction(function () use (&$request) {

                $updatableData = $request->toArray();

                $template  =  tap(EmailTemplateWrapper::where(["id" => $updatableData["id"]]))->update(
                    collect($updatableData)->only([
                        "name",
                        "template"
                    ])->toArray()
                )


                    ->first();

                //    if the template is active then other templates of this type will deactive
                if ($template->is_active) {
                    EmailTemplateWrapper::where("id", "!=", $template->id)
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
            return response()->json(["message" => $e->getMessage()],500);
        }
    }
    /**
     *
     * @OA\Get(
     *      path="/v1.0/email-template-wrappers/{perPage}",
     *      operationId="getEmailTemplateWrappers",
     *      tags={"template_management.wrapper.email"},
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
     *      summary="This method is to get email template  wrappers ",
     *      description="This method is to get email template wrappers",
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

    public function getEmailTemplateWrappers($perPage, Request $request)
    {
        try {



            $templateQuery = new EmailTemplateWrapper();

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
            return response()->json(["message" => $e->getMessage()],500);
        }
    }


     /**
     *
     * @OA\Get(
     *      path="/v1.0/email-template-wrappers/single/{id}",
     *      operationId="getEmailTemplateWrapperById",
     *      tags={"template_management.wrapper.email"},
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
     *      summary="This method is to get email template wrapper by id",
     *      description="This method is to get email template wrapper by id",
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

    public function getEmailTemplateWrapperById($id, Request $request)
    {
        try {



            $template = EmailTemplateWrapper::where([
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

            return response()->json(["message" => $e->getMessage()],500);
        }
    }


}
