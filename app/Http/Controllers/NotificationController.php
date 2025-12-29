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


    // /**
    //  *
    //  * @OA\Post(
    //  *      path="/v1.0/notification",
    //  *      operationId="createNotification",
    //  *      tags={"notification"},
    //  *       security={
    //  *           {"bearerAuth": {}}
    //  *       },
    //  *      summary="This method is to store notification",
    //  *      description="This method is to store notification",

    //  *  @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *            required={"message","receiver_id"},
    //  *            @OA\Property(property="receiver_id", type="integer", example=1, description="ID of the user to receive the notification"),
    //  *            @OA\Property(property="title", type="string", example="Notification Title"),
    //  *            @OA\Property(property="message", type="string", example="Notification message content"),
    //  *            @OA\Property(property="business_id", type="integer", nullable=true, example=1),
    //  *            @OA\Property(property="type", type="string", example="info"),
    //  *            @OA\Property(property="link", type="string", nullable=true, example="https://example.com"),
    //  *            @OA\Property(property="priority", type="string", nullable=true, example="high"),
    //  *            @OA\Property(property="entity_id", type="integer", nullable=true, example=123),
    //  *            @OA\Property(property="entity_ids", type="array", nullable=true, @OA\Items(type="integer"), example={1,2,3}),
    //  *         ),
    //  *      ),
    //  *      @OA\Response(
    //  *          response=200,
    //  *          description="Successful operation",
    //  *          @OA\JsonContent(
    //  *              @OA\Property(property="success", type="boolean", example=true),
    //  *              @OA\Property(property="message", type="string", example="Notification created successfully"),
    //  *              @OA\Property(property="data", type="object",
    //  *                  @OA\Property(property="id", type="integer", example=1),
    //  *                  @OA\Property(property="receiver_id", type="integer", example=1),
    //  *                  @OA\Property(property="sender_id", type="integer", example=2),
    //  *                  @OA\Property(property="business_id", type="integer", nullable=true, example=1),
    //  *                  @OA\Property(property="sender_type", type="string", example="superadmin"),
    //  *                  @OA\Property(property="message", type="string", example="Notification message content"),
    //  *                  @OA\Property(property="status", type="string", example="unread"),
    //  *                  @OA\Property(property="title", type="string", example="Notification Title"),
    //  *                  @OA\Property(property="type", type="string", example="info"),
    //  *                  @OA\Property(property="read_at", type="string", nullable=true, format="date-time"),
    //  *                  @OA\Property(property="link", type="string", nullable=true, example="https://example.com"),
    //  *                  @OA\Property(property="priority", type="string", nullable=true, example="high"),
    //  *                  @OA\Property(property="entity_id", type="integer", nullable=true, example=123),
    //  *                  @OA\Property(property="entity_ids", type="array", nullable=true, @OA\Items(type="integer"), example={1,2,3})
    //  *              )
    //  *          )
    //  *      ),
    //  *      @OA\Response(
    //  *          response=401,
    //  *          description="Unauthenticated",
    //  * @OA\JsonContent(),
    //  *      ),
    //  *        @OA\Response(
    //  *          response=422,
    //  *          description="Unprocessable Content",
    //  *    @OA\JsonContent(),
    //  *      ),
    //  *      @OA\Response(
    //  *          response=403,
    //  *          description="Forbidden",
    //  *   @OA\Response(
    //  *      response=400,
    //  *      description="Bad Request"
    //  *   ),
    //  * @OA\Response(
    //  *      response=404,
    //  *      description="not found"
    //  *   ),
    //  *@OA\JsonContent()
    //  *      )
    //  *     )
    //  */


    // public function createNotification(NotificationRequest $request)
    // {

    //     $body = $request->validated();
    //     $body["sender_id"] = $request->user()->id;
    //     $body["status"] = "unread";
    //     $body["sender_type"] = $request->user()->hasRole("superadmin") ? "superadmin" : "";
    //     $notification = Notification::create($body);

    //     //  SEND RESPONSE
    //     return response([
    //         "success" => true,
    //         "message" => "Notification created successfully",
    //         "data" => $notification
    //     ], 200);
    // }


    // ##################################################
    // This method is to update notification
    // ##################################################

    // /**
    //  *
    //  * @OA\Patch(
    //  *      path="/v1.0/notification/{notificationId}",
    //  *      operationId="updateNotification",
    //  *      tags={"notification"},
    //  *       security={
    //  *           {"bearerAuth": {}}
    //  *       },
    //  *      summary="This method is to update notification",
    //  *      description="This method is to update notification",
    //  *  @OA\Parameter(
    //  * name="notificationId",
    //  * in="path",
    //  * description="notificationId",
    //  * required=true,
    //  * example="1"
    //  * ),
    //  *  @OA\RequestBody(
    //  *         required=false,
    //  *         @OA\JsonContent(
    //  *            @OA\Property(property="title", type="string", example="Updated Title"),
    //  *
    //  *            @OA\Property(property="message", type="string", example="Updated message content"),
    //  *            @OA\Property(property="status", type="string", enum={"read", "unread"}, example="read"),
    //  *            @OA\Property(property="read_at", type="string", format="date-time", nullable=true, example="2025-12-30T10:00:00Z"),
    //  *            @OA\Property(property="type", type="string", example="warning"),
    //  *            @OA\Property(property="link", type="string", nullable=true, example="https://updated-link.com"),
    //  *            @OA\Property(property="priority", type="string", nullable=true, example="low"),
    //  *            @OA\Property(property="entity_id", type="integer", nullable=true, example=456),
    //  *            @OA\Property(property="entity_ids", type="array", nullable=true, @OA\Items(type="integer"), example={4,5,6}),
    //  *      ),
    //  *      @OA\Response(
    //  *          response=200,
    //  *          description="Successful operation",
    //  *          @OA\JsonContent(
    //  *              @OA\Property(property="success", type="boolean", example=true),
    //  *              @OA\Property(property="message", type="string", example="Notification updated successfully"),
    //  *              @OA\Property(property="data", type="object",
    //  *                  @OA\Property(property="id", type="integer", example=1),
    //  *                  @OA\Property(property="receiver_id", type="integer", example=1),
    //  *                  @OA\Property(property="sender_id", type="integer", example=2),
    //  *                  @OA\Property(property="business_id", type="integer", nullable=true, example=1),
    //  *                  @OA\Property(property="sender_type", type="string", example="superadmin"),
    //  *                  @OA\Property(property="message", type="string", example="Updated message content"),
    //  *                  @OA\Property(property="status", type="string", example="read"),
    //  *                  @OA\Property(property="title", type="string", example="Updated Title"),
    //  *                  @OA\Property(property="type", type="string", example="warning"),
    //  *                  @OA\Property(property="read_at", type="string", nullable=true, format="date-time"),
    //  *                  @OA\Property(property="link", type="string", nullable=true, example="https://updated-link.com"),
    //  *                  @OA\Property(property="priority", type="string", nullable=true, example="low"),
    //  *                  @OA\Property(property="entity_id", type="integer", nullable=true, example=456),
    //  *                  @OA\Property(property="entity_ids", type="array", nullable=true, @OA\Items(type="integer"), example={4,5,6})
    //  *              )
    //  *          )
    //  *      ),
    //  *      @OA\Response(
    //  *          response=401,
    //  *          description="Unauthenticated",
    //  * @OA\JsonContent(),
    //  *      ),
    //  *        @OA\Response(
    //  *          response=422,
    //  *          description="Unprocessable Content",
    //  *    @OA\JsonContent(),
    //  *      ),
    //  *      @OA\Response(
    //  *          response=403,
    //  *          description="Forbidden",
    //  *   @OA\Response(
    //  *      response=400,
    //  *      description="Bad Request"
    //  *   ),
    //  * @OA\Response(
    //  *      response=404,
    //  *      description="not found"
    //  *   ),
    //  *@OA\JsonContent()
    //  *      )
    //  *     )
    //  */
    // public function updateNotification($notificationId, NotificationRequest $request)
    // {

    //     // VALIDATE REQUEST
    //     $request_payload = $request->validated();

    //     $notification = Notification::find($notificationId);

    //     if (!$notification) {
    //         return response([
    //             "success" => false,
    //             "message" => "Notification not found",
    //         ], 404);
    //     }

    //     // UPDATE Notification
    //     $notification->update($request_payload);

    //     // SEND RESPONSE
    //     return response([
    //         "success" => true,
    //         "message" => "Notification updated successfully",
    //         "data" => $notification
    //     ], 200);
    // }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/notification",
     *      operationId="getNotification",
     *      tags={"notification"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="This method is to get notification by receiver_id",
     *      description="This method is to get notification by receiver_id with pagination and filters",
     *
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Page number for pagination",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          description="Number of items per page",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          in="query",
     *          description="Filter by notification status",
     *          required=false,
     *          @OA\Schema(type="string", enum={"read", "unread"})
     *      ),
     *      @OA\Parameter(
     *          name="search_key",
     *          in="query",
     *          description="Search in title and message",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="order_by",
     *          in="query",
     *          description="Field to order by (e.g., created_at, title)",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="sort_order",
     *          in="query",
     *          description="Sort order",
     *          required=false,
     *          @OA\Schema(type="string", enum={"asc", "desc"})
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Notifications retrieved successfully"),
     *              @OA\Property(property="meta", type="object",
     *                  @OA\Property(property="total", type="integer", example=100),
     *                  @OA\Property(property="per_page", type="integer", example=10),
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="last_page", type="integer", example=10)
     *              ),
     *              @OA\Property(property="data", type="array",
     *                  @OA\Items(type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="receiver_id", type="integer", example=1),
     *                      @OA\Property(property="sender_id", type="integer", example=2),
     *                      @OA\Property(property="business_id", type="integer", nullable=true, example=1),
     *                      @OA\Property(property="sender_type", type="string", example="superadmin"),
     *                      @OA\Property(property="message", type="string", example="Notification message content"),
     *                      @OA\Property(property="status", type="string", example="unread"),
     *                      @OA\Property(property="title", type="string", example="Notification Title"),
     *                      @OA\Property(property="type", type="string", example="info"),
     *                      @OA\Property(property="read_at", type="string", nullable=true, format="date-time"),
     *                      @OA\Property(property="link", type="string", nullable=true, example="https://example.com"),
     *                      @OA\Property(property="priority", type="string", nullable=true, example="high"),
     *                      @OA\Property(property="entity_id", type="integer", nullable=true, example=123),
     *                      @OA\Property(property="entity_ids", type="array", nullable=true, @OA\Items(type="integer"), example={1,2,3}),
     *                      @OA\Property(property="created_at", type="string", format="date-time"),
     *                      @OA\Property(property="updated_at", type="string", format="date-time")
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Not found",
     *          @OA\JsonContent()
     *      )
     *     )
     */
    public function getNotification(Request $request)
    {
        $query = Notification::where(["receiver_id" => $request->user()->id])->filters();

        $notification = retrieve_data($query);

        return response([
            "success" => true,
            "message" => "Notifications retrieved successfully",
            "meta" => $notification["meta"],
            "data" => $notification["data"]
        ], 200);
    }

    // ##################################################
    // This method is to change notification status
    // ##################################################

    /**
     *
     * @OA\Patch(
     *      path="/v1.0/notification/{notificationId}/status",
     *      operationId="changeNotificationStatus",
     *      tags={"notification"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to change notification status to read",
     *      description="This method is to change notification status to read and set read_at timestamp",
     *        @OA\Parameter(
     *         name="notificationId",
     *         in="path",
     *         description="notificationId",
     *         required=true,
     *         example="1"
     *      ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Notification status changed successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="receiver_id", type="integer", example=1),
     *                  @OA\Property(property="sender_id", type="integer", example=2),
     *                  @OA\Property(property="business_id", type="integer", nullable=true, example=1),
     *                  @OA\Property(property="sender_type", type="string", example="superadmin"),
     *                  @OA\Property(property="message", type="string", example="Notification message content"),
     *                  @OA\Property(property="status", type="string", example="read"),
     *                  @OA\Property(property="title", type="string", example="Notification Title"),
     *                  @OA\Property(property="type", type="string", example="info"),
     *                  @OA\Property(property="read_at", type="string", format="date-time"),
     *                  @OA\Property(property="link", type="string", nullable=true, example="https://example.com"),
     *                  @OA\Property(property="priority", type="string", nullable=true, example="high"),
     *                  @OA\Property(property="entity_id", type="integer", nullable=true, example=123),
     *                  @OA\Property(property="entity_ids", type="array", nullable=true, @OA\Items(type="integer"), example={1,2,3})
     *              )
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


    public function changeNotificationStatus($notificationId, Request $request)
    {

        // FIND Notification
        $notification = Notification::find($notificationId);

        if (!$notification) {
            return response([
                "success" => false,
                "message" => "Notification not found"
            ], 404);
        }

        // CHECK IF USER IS THE RECEIVER
        if ($notification->receiver_id !== $request->user()->id) {
            return response([
                "success" => false,
                "message" => "You are not authorized to change this notification status"
            ], 403);
        }

        // UPDATE STATUS TO READ AND SET READ_AT
        $notification->update([
            'status' => 'read',
            'read_at' => now()
        ]);

        // SEND RESPONSE
        return response([
            "success" => true,
            "message" => "Notification status changed successfully",
            "data" => $notification
        ], 200);
    }

    // ##################################################
    // This method is to mark all notifications as read
    // ##################################################

    /**
     *
     * @OA\Patch(
     *      path="/v1.0/notification/mark-all-read",
     *      operationId="markAsAllRead",
     *      tags={"notification"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to mark all notifications as read for the current user",
     *      description="This method is to mark all unread notifications as read and set read_at timestamp",

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="All notifications marked as read successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="updated_count", type="integer", example=5)
     *              )
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
     *@OA\JsonContent()
     *      )
     *     )
     */


    public function markAsAllRead(Request $request)
    {

        // UPDATE ALL UNREAD NOTIFICATIONS FOR THE CURRENT USER
        $updatedCount = Notification::where('receiver_id', $request->user()->id)
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now()
            ]);

        // SEND RESPONSE
        return response([
            "success" => true,
            "message" => "All notifications marked as read successfully",
            "data" => [
                "updated_count" => $updatedCount
            ]
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
