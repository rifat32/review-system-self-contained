<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Business;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripeController extends Controller
{


    public function redirectUserToStripe(Request $request)
    {
        $id = $request->id;

        // Check if the string is at least 20 characters long to ensure it has enough characters to remove
        if (strlen($id) >= 20) {
            // Remove the first ten characters and the last ten characters
            $trimmed_id = substr($id, 10, -10);
            // $trimmedId now contains the string with the first ten and last ten characters removed
        } else {
            throw new Exception("invalid id");
        }
        $order = Order::findOrFail($trimmed_id);
        $user = User::findOrFail($order->customer_id);
        $business = Business::findOrFail($order->business_id);




        Stripe::setApiKey($business->STRIPE_SECRET);
        Stripe::setClientId($business->STRIPE_KEY);



        $existing_customer = \Stripe\Customer::all(["email" => $user->email], ["limit" => 1])->data;
        if (!empty($existing_customer)) {
            // Customer already exists, retrieve the existing customer
            $stripe_customer = $existing_customer[0];
        } else {
            // Customer doesn't exist, create a new one
            $stripe_customer = \Stripe\Customer::create([
                'email' => $user->email,
            ]);
        }



        if ((($order->amount * 100) - ($order->discount * 100) + ($order->tax * 100)) <= 0) {
            return response()->json([
                "message" => "you can not pay 0",
                "order" => $order
            ], 403);
        }


        $session_data = [
            'payment_method_types' => ['card'],
            'client_reference_id' => $order->id,
            'metadata' => [
                'product_id' => '123',
                'product_description' => 'Your Service set up amount',
            ],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'GBP',
                        'product_data' => [
                            'name' => 'Your order amount',
                        ],
                        'unit_amount' => $order->amount * 100, // Amount in cents
                    ],
                    'quantity' => 1,
                ],


            ],
            'customer' => $stripe_customer,
            'mode' => 'payment',
            'success_url' => route("order.success_payment"),
            'cancel_url' => route("order.failed_payment"),
        ];

        // Add discount line item only if discount amount is greater than 0 and not null
        if (!empty($order->discount) && $order->discount > 0) {
            $session_data['line_items'][] =   [
                'price_data' => [
                    'currency' => 'GBP',
                    'product_data' => [
                        'name' => 'Discount', // Name of the discount
                    ],
                    'unit_amount' => - ($order->discount * 100), // Negative value to represent discount
                    'quantity' => 1,
                ],
            ];
        }

        // Add tax line item only if discount amount is greater than 0 and not null
        if (!empty($order->tax) && $order->tax > 0) {
            $session_data['line_items'][] =   [
                'price_data' => [
                    'currency' => 'GBP',
                    'product_data' => [
                        'name' => 'Discount', // Name of the discount
                    ],
                    'unit_amount' => ($order->tax * 100), // Negative value to represent discount
                    'quantity' => 1,
                ],
            ];
        }



        $session = Session::create($session_data);



        return redirect()->to($session->url);
    }


    public function stripePaymentSuccess(Request $request)
    {
        return redirect()->away(env("FRONT_END_URL") . '/customer/orders');
    }
    public function stripePaymentFailed(Request $request)
    {
        return redirect()->away(env("FRONT_END_URL") . '/customer/orders');
    }



    /**
     *
     * @OA\Patch(
     *      path="/business/UpdateResturantStripeDetails/{restaurentId}",
     *      operationId="UpdateResturantStripeDetails",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}},
     *  *            {"pin": {}}
     *       },
     *      summary="This method is to update business",
     *      description="This method is to update business",
     *  *            @OA\Parameter(
     *         name="restaurentId",
     *         in="path",
     *         description="method",
     *         required=true,
     * example="1"
     *      ),
     *
     *            @OA\Parameter(
     *         name="_method",
     *         in="query",
     *         description="method",
     *         required=true,
     * example="PATCH"
     *      ),
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={ "Name","Layout","Address","PostCode" },
     *
     *      *  *       @OA\Property(property="STRIPE_KEY", type="string", format="string",example="string"),
     *      *  *       @OA\Property(property="STRIPE_SECRET", type="string", format="string",example="string"),

     *
     *
     *
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

    public function UpdateResturantStripeDetails($restaurentId, Request $request)
    {


        $checkBusiness =    Business::where(["id" => $restaurentId])->first();


        if (!$checkBusiness) {
            return response()->json([
                "message" => "you don't have a valid business"
            ], 401);
        }


        if (!($checkBusiness->pin == $request->header("pin"))) {
            return response()->json([
                "message" => "invalid pin"
            ], 401);
        }


        if ($checkBusiness->OwnerID != $request->user()->id) {
            return response()->json(["message" => "This is not your business", 401]);
        }


        $data["business"] = tap(Business::where(["id" => $restaurentId]))->update($request->only(

            "STRIPE_KEY",
            "STRIPE_SECRET",


        ))
            // ->with("somthing")
            ->first();



        if (!$data["business"]) {
            return response()->json(["message" => "No Business Found"], 404);
        }


        $data["message"] = "Business updates successfully";




        return response()->json($data, 200);
    }

    /**
     *
     * @OA\Get(
     *      path="/business/getResturantStripeDetails/{id}",
     *      operationId="GetResturantStripeDetails",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get business stripe details by id",
     *      description="This method is to get business stripe details by id",
     *  @OA\Parameter(
     * name="id",
     * in="path",
     * description="id",
     * required=true,
     * example="1"
     * ),
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
    public function GetResturantStripeDetails($businessId)
    {
        $business =   Business::with("owner")->where(["id" => $businessId])->first();


        if (!$business) {
            return response()->json([
                "message" => "you don't have a valid business" . $businessId
            ], 401);
        }

        if ($business->OwnerID != auth()->user()->id) {
            return response()->json(["message" => "This is not your business", 401]);
        }

        $data["enable_customer_order_payment"] = $business->enable_customer_order_payment;
        $data["STRIPE_KEY"] = $business->STRIPE_KEY;
        $data["STRIPE_SECRET"] = $business->STRIPE_SECRET;



        return response($data, 200);
    }
    /**
     *
     * @OA\Get(
     *      path="/client/business/getResturantStripeDetails/{id}",
     *      operationId="GetResturantStripeDetailsClient",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get business stripe details by id",
     *      description="This method is to get business stripe details by id",
     *  @OA\Parameter(
     * name="id",
     * in="path",
     * description="id",
     * required=true,
     * example="1"
     * ),
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
    public function GetResturantStripeDetailsClient($businessId)
    {
        $business =   Business::with("owner")->where(["id" => $businessId])->first();


        if (!$business) {
            return response()->json([
                "message" => "you don't have a valid business" . $businessId
            ], 401);
        }

        // if ($business->OwnerID != auth()->user()->id) {
        //     return response()->json(["message" => "This is not your business", 401]);
        // }

        $data["enable_customer_order_payment"] = $business->enable_customer_order_payment;
        $data["STRIPE_KEY"] = $business->STRIPE_KEY;
        // $data["STRIPE_SECRET"] = $business->STRIPE_SECRET;



        return response($data, 200);
    }




}
