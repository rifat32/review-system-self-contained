<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationRequest;
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
     *      path="/v1.0/notification",
     *      operationId="createNotification",
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
     *            @OA\Property(property="title", type="string", format="string",example="hello"),
     *             @OA\Property(property="message", type="string", format="string",example="hello"),
     *  @OA\Property(property="reciever_id", type="string", format="string",example="1"),


     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Notification created successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
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
     *   @OA\Response(
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


    public function createNotification(NotificationRequest $request)
    {

        $body = $request->validated();
        $body["sender_id"] = $request->user()->id;
        $body["status"] = "unread";
        $body["sender_type"] = $request->user()->hasRole("superadmin") ? "superadmin" : "";
        $notification =  Notification::create($body);

        //  SEND RESPONSE
        return response([
            "success" => true,
            "message" => "Notification created successfully",
            "data" => $notification
        ], 200);
    }


    // ##################################################
    // This method is to update notification
    // ##################################################

    /**
     *
     * @OA\Patch(
     *      path="/v1.0/notification/{notificationId}",
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
     *             @OA\Property(property="status", type="string", format="string",example="test"),

     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Notification updated successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
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
     *   @OA\Response(
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
    public function updateNotification($notificationId, NotificationRequest $request)
    {

        // VALIDATE REQUEST
        $request_payload = $request->validated();

        $notification =    Notification::find($notificationId);

        if (!$notification) {
            return response([
                "success" => false,
                "message" => "Notification not found",
            ], 404);
        }

        // UPDATE Notification
        $notification->update($request_payload);

        // SEND RESPONSE
        return response([
            "success" => true,
            "message" => "Notification updated successfully",
            "data" => $notification
        ], 200);
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/notification",
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
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Notifications retrieved successfully"),
     *              @OA\Property(property="meta", type="object",
     *                  @OA\Property(property="total", type="integer", example=10)
     *              ),
     *              @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *          )
     *      ),
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
     *   @OA\Response(
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
    public function getNotification(Request $request)
    {
        $notification = Notification::where(["reciever_id" => $request->user()->id])->get();

        return response([
            "success" => true,
            "message" => "Notifications retrieved successfully",
            "meta" => [
                "total" => $notification->count()
            ],
            "data" => $notification
        ], 200);
    }

    // ##################################################
    // This method is to delete notification
    // ##################################################

    /**
     *
     * @OA\Delete(
     *      path="/v1.0/notification/{notificationId}",
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
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Notification deleted successfully")
     *          )
     *      ),
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
     *   @OA\Response(
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

        // FIND Notification
        $notification = Notification::find($notificationId);

        if (!$notification) {
            return response([
                "success" => false,
                "message" => "Notification not found"
            ], 404);
        }

        // DELETE Notification
        $notification->delete();


        // SEND RESPONSE
        return response([
            "success" => true,
            "message" => "Notification deleted successfully"
        ], 200);
    }
}
