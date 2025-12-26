<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    /**
     * @OA\Post(
     *      path="/v1.0/client/email/send-email",
     *      operationId="sendEmail",
     *      tags={"email_management.feed_genius"},
     *      summary="Send contact form email",
     *      description="Send a contact form email with user details",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"first_name","last_name","email","subject","message"},
     *              @OA\Property(property="first_name", type="string", example="John"),
     *              @OA\Property(property="last_name", type="string", example="Doe"),
     *              @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *              @OA\Property(property="subject", type="string", example="Inquiry about services"),
     *              @OA\Property(property="message", type="string", example="Hello, I would like to know more about your services.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Email sent successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="string", example="Thank you! Your message has been sent successfully.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="The given data was invalid."),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="error", type="string", example="Sorry, something went wrong. Please try again later.")
     *          )
     *      )
     * )
     */
    public function sendEmail(Request $request)
    {
        // 1. Validate the form data based on the image fields
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email',
            'subject'    => 'required|string',
            'message'    => 'required|string|min:10',
        ]);

        // 2. Define the Receiver
        $receiverEmail = "info@feedgenius.ai";

        // 3. Send the Email
        try {
            Mail::to($receiverEmail)->send(new ContactFormMail($validated));

            return response()->json(['success' => 'Thank you! Your message has been sent successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, something went wrong. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
