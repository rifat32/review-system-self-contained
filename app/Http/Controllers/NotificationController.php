<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    // ##################################################
    // This method is to store notification
    // ##################################################


     /**
        *
     * @OA\Post(
     *      path="/notification",
     *      operationId="storeNotification",
     *      tags={"notification"},
 *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store notification",
     *      description="This method is to store notification",

     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"message","reciever_id"},
     * *             @OA\Property(property="title", type="string", format="string",example="hello"),
     *             @OA\Property(property="message", type="string", format="string",example="hello"),
     *  @OA\Property(property="reciever_id", type="string", format="string",example="1"),


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
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */


    public function storeNotification(Request $request)
    {

        $body = $request->toArray();
        $body["sender_id"] = $request->user()->id;
        $body["status"] = "unRead";
        $body["sender_type"] = $request->user()->hasRole("superadmin")?"superadmin":"";
        $notification =  Notification::create($body);

        return response($notification, 200);
    }
    // ##################################################
    // This method is to update notification
    // ##################################################

     /**
        *
     * @OA\Patch(
     *      path="/notification/{notificationId}",
     *      operationId="updateNotification",
     *      tags={"notification"},
 *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update notification",
     *      description="This method is to update notification",
     *  @OA\Parameter(
* name="notificationId",
* in="path",
* description="notificationId",
* required=true,
* example="1"
* ),
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"message"},
     *
     *             @OA\Property(property="message", type="string", format="string",example="test@g.c"),
     *  *             @OA\Property(property="status", type="string", format="string",example="test"),

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
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function updateNotification($notificationId, Request $request)
    {



        $notification =    tap(Notification::where(["id" => $notificationId]))->update(
            $request->only(
                "message",
                "status",

            )

        )
            // ->with("somthing")

            ->first();
        return response($notification, 200);
    }
    // ##################################################
    // This method is to get notification by reciever_id
    // ##################################################

/**
        *
     * @OA\Get(
     *      path="/notification",
     *      operationId="getNotification",
     *      tags={"notification"},
 *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get notification by reciever_id",
     *      description="This method is to get notification by reciever_id",
     *

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
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function getNotification( Request $request)
    {
        $data["content"] = Notification::where(["reciever_id" => $request->user()->id])->get();
        $data["total"] = $data["content"]->count();

        return response($data, 200);
    }

    // ##################################################
    // This method is to delete notification
    // ##################################################

/**
        *
     * @OA\Delete(
     *      path="/notification/{notificationId}",
     *      operationId="deleteNotification",
     *      tags={"notification"},
 *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to delete notification",
     *      description="This method is to delete notification",
     *        @OA\Parameter(
     *         name="notificationId",
     *         in="path",
     *         description="notificationId",
     *         required=true,
     *      ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
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
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */


    public function deleteNotification($notificationId, Request $request)
    {
        Notification::where([
            "id" => $notificationId,
        ])
            ->delete();



        return response(["message" => "ok"], 201);
    }
}
