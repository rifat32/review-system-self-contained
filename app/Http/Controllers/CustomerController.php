<?php

namespace App\Http\Controllers;

use App\Http\Utils\ErrorUtil;

use App\Models\Business;

use App\Models\User;
use App\Services\CustomerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomerController extends Controller
{
    use ErrorUtil;

    protected $customerService;

    /**
     * Constructor to inject CustomerService
     *
     * @param CustomerService $customerService
     */
    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }
    /**
     *
     * @OA\Get(
     *      path="/v1.0/customers",
     *      operationId="getCustomers",
     *      tags={"reports"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Get customers with advanced filtering and pagination",
     *      description="Retrieve a list of customers with various filtering options including ratings, reviews, frequency visits, and pagination support",
     *
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          description="Number of customers per page for pagination",
     *          required=false,
     *          example="15",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Page number for pagination",
     *          required=false,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="order_by",
     *          in="query",
     *          description="Field to sort by (e.g., first_Name, last_Name, email, created_at)",
     *          required=false,
     *          example="first_Name",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_order",
     *          in="query",
     *          description="Sort direction (ASC or DESC)",
     *          required=false,
     *          example="DESC",
     *          @OA\Schema(type="string", enum={"ASC", "DESC"})
     *      ),
     *
     *      @OA\Parameter(
     *          name="rating",
     *          in="query",
     *          description="Filter customers by their review rating (1-5)",
     *          required=false,
     *          example="4",
     *          @OA\Schema(type="integer", minimum=1, maximum=5)
     *      ),
     *
     *      @OA\Parameter(
     *          name="review_start_date",
     *          in="query",
     *          description="Start date for filtering reviews (YYYY-MM-DD)",
     *          required=false,
     *          example="2025-01-01",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Parameter(
     *          name="review_end_date",
     *          in="query",
     *          description="End date for filtering reviews (YYYY-MM-DD)",
     *          required=false,
     *          example="2025-12-31",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Parameter(
     *          name="review_keyword",
     *          in="query",
     *          description="Keyword to search within review comments",
     *          required=false,
     *          example="excellent service",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="frequency_visit",
     *          in="query",
     *          description="Filter by visit frequency category",
     *          required=false,
     *          example="Regular",
     *          @OA\Schema(type="string", enum={"New", "Regular", "VIP"})
     *      ),
     *
     *      @OA\Parameter(
     *          name="name",
     *          in="query",
     *          description="Filter customers by first or last name",
     *          required=false,
     *          example="John",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="email",
     *          in="query",
     *          description="Filter customers by email address",
     *          required=false,
     *          example="customer@example.com",
     *          @OA\Schema(type="string", format="email")
     *      ),
     *
     *      @OA\Parameter(
     *          name="phone",
     *          in="query",
     *          description="Filter customers by phone number",
     *          required=false,
     *          example="+1234567890",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="search_key",
     *          in="query",
     *          description="General search term for name, email, or phone",
     *          required=false,
     *          example="john",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          description="Filter customers created after this date (YYYY-MM-DD)",
     *          required=false,
     *          example="2025-01-01",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          description="Filter customers created before this date (YYYY-MM-DD)",
     *          required=false,
     *          example="2025-12-31",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Customers retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Customer list retrieved successfully."),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="first_Name", type="string", example="John"),
     *                      @OA\Property(property="last_Name", type="string", example="Doe"),
     *                      @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                      @OA\Property(property="phone", type="string", example="+1234567890"),
     *                      @OA\Property(property="positive_reviews", type="integer", example=5),
     *                      @OA\Property(property="negative_reviews", type="integer", example=1),
     *                      @OA\Property(property="avg_satisfaction", type="number", format="float", example=4.2),
     *                      @OA\Property(
     *                          property="common_complaints",
     *                          type="array",
     *                          @OA\Items(type="object")
     *                      ),
     *                      @OA\Property(
     *                          property="customer_comments_trends",
     *                          type="array",
     *                          @OA\Items(type="object")
     *                      )
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="meta",
     *                  type="object",
     *                  description="Pagination metadata (only when per_page is provided)",
     *                  @OA\Property(property="total", type="integer", example=150),
     *                  @OA\Property(property="per_page", type="integer", example=15),
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="skip", type="integer", example=0),
     *                  @OA\Property(property="total_pages", type="integer", example=10)
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Internal server error")
     *          )
     *      )
     * )
     */

    public function getCustomers(Request $request): JsonResponse
    {
        try {
            $this->storeActivity($request, "");

            $business = Business::where([
                "OwnerID" => auth()->user()->id
            ])
                ->first();

            // CHECK IF BUSINESS EXISTS
            if (!$business) {
                throw new Exception("Business not found for the authenticated user", 404);
            }

            // BUILD QUERY WITH CUSTOMER FILTER SCOPE
            $customersQuery = User::filterCustomers();

            // USE RETRIEVE_DATA HELPER FOR PAGINATION AND DATA RETRIEVAL
            $result = retrieve_data($customersQuery);

            // ADD CUSTOMER DATA TO EACH USER
            $result['data'] = collect($result['data'])->map(function ($user) use ($business) {
                return $this->customerService->enrichCustomerWithData($user, $business);
            })->toArray();

            return response()->json([
                "success" => true,
                "message" => "Customer list retrieved successfully.",
                "data" => $result['data'],
                "meta" => $result['meta']
            ], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    public function addCustomerData($user,$business)
    {


      

    // Fetch positive reviews separately
    $positive_reviews = ReviewNew::where('review_news.business_id', $business->id)
        ->where('review_news.rate', '>=', 4)
        ->where('review_news.user_id', $user->id)
            ->globalFilters()
        ->count();
    $user->positive_reviews = $positive_reviews;

    // Fetch negative reviews separately
    $negative_reviews = ReviewNew::where('review_news.business_id', $business->id)
        ->where('review_news.rate', '<=', 2)
        ->where('review_news.user_id', $user->id)
            ->globalFilters()
        ->count();
    $user->negative_reviews = $negative_reviews;

    // Fetch common complaints separately
    $common_complaints = ReviewNew::selectRaw('COUNT(id) as complaint_count, SUBSTRING_INDEX(comment, " ", 3) as complaint_snippet')
        ->where('review_news.business_id', $business->id)
        ->where('review_news.user_id', $user->id)
        ->groupBy('complaint_snippet')
        ->havingRaw('complaint_count > 2')
            ->globalFilters()
            ->orderBy('order_no', 'asc')
        ->get();
    $user->common_complaints = $common_complaints;

    // Fetch satisfaction scores separately
    $satisfaction_scores = ReviewNew::where('review_news.business_id', $business->id)
        ->where('review_news.user_id', $user->id)
            ->globalFilters()

        ->avg('review_news.rate');
    $user->avg_satisfaction = $satisfaction_scores;

    // Fetch customer comments trends separately
    $customer_comments_trends = ReviewNew::selectRaw('comment, COUNT(*) as comment_count')
        ->where('review_news.business_id', $business->id)
        ->where('review_news.user_id', $user->id)
        ->groupBy('comment')
        ->orderByDesc('comment_count')
            ->globalFilters()
            ->orderBy('order_no', 'asc')
        ->limit(5)
        ->get();
    $user->customer_comments_trends = $customer_comments_trends;

        return $user;
    }
}
