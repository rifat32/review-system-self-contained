<?php

namespace App\Http\Controllers;

use App\Helpers\AIProcessor;
use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\Business;
use App\Models\ReviewNew;
use App\Services\AIProcessor\AIProcessorService;
use App\Services\Branch\BranchService;
use App\Services\Review\ReviewService;
use App\Services\Review\RecentReviewService;
use App\Services\Rule\RuleEngineService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BranchController extends Controller
{
    private $branchService;
    private $reviewService;
    private AIProcessorService $aiProcessorService;
    private $recentReviewService;

    public function __construct(
        BranchService $branchService,
        ReviewService $reviewService,
        AIProcessorService $aiProcessorService,
        RecentReviewService $recentReviewService
    ) {
        $this->branchService = $branchService;
        $this->reviewService = $reviewService;
        $this->aiProcessorService = $aiProcessorService;
        $this->recentReviewService = $recentReviewService;
    }

    /**
     * @OA\Get(
     *      path="/v1.0/branches",
     *      operationId="getBranches",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get all branches for the authenticated user's businesses",
     *      description="Retrieve a list of all branches belonging to businesses owned by the authenticated user.",
     *
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Page number for pagination",
     *          required=false,
     *          @OA\Schema(type="integer", example=1)
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          description="Number of items per page",
     *          required=false,
     *          @OA\Schema(type="integer", example=10)
     *      ),
     *      @OA\Parameter(
     *          name="search_key",
     *          in="query",
     *          description="Search key to filter branches by name or branch code",
     *          required=false,
     *          @OA\Schema(type="string", example="Main")
     *      ),
     *      @OA\Parameter(
     *          name="sort_by",
     *          in="query",
     *          description="Field to sort branches by",
     *          required=false,
     *          @OA\Schema(type="string", example="created_at", enum={"name", "created_at", "updated_at"})
     *      ),
     *      @OA\Parameter(
     *          name="sort_order",
     *          in="query",
     *          description="Sort order (asc or desc)",
     *          required=false,
     *          @OA\Schema(type="string", example="desc", enum={"asc", "desc"})
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Branches retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branches retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="business_id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="Main Branch"),
     *                      @OA\Property(property="address", type="string", example="123 Main St"),
     *                      @OA\Property(property="phone", type="string", example="+1234567890"),
     *                      @OA\Property(property="email", type="string", example="branch@example.com"),
     *                      @OA\Property(property="is_active", type="boolean", example=true),
     *                      @OA\Property(property="is_default", type="boolean", example=false),
     *                      @OA\Property(property="is_geo_enabled", type="boolean", example=false),
     *                      @OA\Property(property="branch_code", type="string", example="BR001"),
     *                      @OA\Property(property="lat", type="string", format="float", example="40.7128"),
     *                      @OA\Property(property="long", type="string", format="float", example="-74.0060"),
     *                      @OA\Property(property="created_at", type="string", format="date-time"),
     *                      @OA\Property(property="updated_at", type="string", format="date-time")
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="summary",
     *                  type="object",
     *                  @OA\Property(property="total_branches", type="integer", example=5),
     *                  @OA\Property(property="avg_rating", type="number", format="float", example=4.2),
     *                  @OA\Property(property="overall_sentiment_score", type="number", format="float", example=0.75, description="Average sentiment score (0.0-1.0)"),
     *                  @OA\Property(property="overall_sentiment_label", type="string", example="positive", description="Sentiment label: positive (>=0.7), neutral (0.4-0.69), or negative (<0.4)")
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
     *      )
     * )
     */

    public function getBranches(Request $request)
    {
        $user = $request->user();
        $businessId = $user->business_id;
        $userBranchId = null;

        if (!$businessId) {
            throw new AuthorizationException('No business found for the authenticated user');
        }

        //|| $user->hasRole('business_owner')
        if ($user->hasRole('branch_manager')) {
            $userBranchId = $user->default_branch_id;
        }


        // BRANCH QUERY
        $query = Branch::withCount([
                'reviews as overall_review_count' => function ($query) {
                    $query->where('is_overall', 1);
                },
                'reviews as survey_review_count' => function ($query) {
                    $query->where('is_overall', 0)
                        ->whereNotNull("survey_id");
                },
            ])
            ->where('business_id', $businessId)
            ->when($userBranchId, function ($query) use ($userBranchId) {
                $query->where('id', $userBranchId);
            })
            ->filters();

        // GET BRANCHES WITH PAGINATED DATA
        $branches = retrieve_data($query);

        // GET SUMMARY DATA
        $branchIds = Branch::where('business_id', $businessId)->pluck('id');



        // SEND RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Branches retrieved successfully',
            'meta' => $branches['meta'],
            'data' => $branches['data'],
        ]);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/branches/overview",
     *      operationId="getBranchOverview",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get branch overview with comparison data",
     *      description="Retrieve aggregated metrics for all branches including total branches, average rating, and overall sentiment with period-over-period comparison.",
     *
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          description="Time period for metrics calculation",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              example="last_30_days",
     *              enum={"last_7_days", "last_30_days", "last_90_days", "this_month", "last_month"}
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          description="Start date for custom date range (DD-MM-YYYY)",
     *          required=false,
     *          @OA\Schema(type="string", format="date")
     *      ),
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          description="End date for custom date range (DD-MM-YYYY)",
     *          required=false,
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Branch overview retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch overview retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(
     *                      property="total_branches",
     *                      type="object",
     *                      @OA\Property(property="value", type="integer", example=10, description="Current period total branches"),
     *                      @OA\Property(property="previous_value", type="integer", example=8, description="Previous period total branches"),
     *                      @OA\Property(property="change_value", type="integer", example=2, description="Difference between current and previous"),
     *                      @OA\Property(property="change_type", type="string", example="increase", enum={"increase", "decrease", "no_change"}, description="Type of change")
     *                  ),
     *                  @OA\Property(
     *                      property="avg_rating",
     *                      type="object",
     *                      @OA\Property(property="value", type="number", format="float", example=4.5, description="Current period average rating"),
     *                      @OA\Property(property="previous_value", type="number", format="float", example=4.2, description="Previous period average rating"),
     *                      @OA\Property(property="change_value", type="number", format="float", example=0.3, description="Difference between current and previous"),
     *                      @OA\Property(property="change_type", type="string", example="increase", enum={"increase", "decrease", "no_change"}, description="Type of change")
     *                  ),
     *                  @OA\Property(
     *                      property="overall_sentiment",
     *                      type="object",
     *                      @OA\Property(property="value", type="number", format="float", example=0.75, description="Current period sentiment score (0.0-1.0)"),
     *                      @OA\Property(property="previous_value", type="number", format="float", example=0.68, description="Previous period sentiment score (0.0-1.0)"),
     *                      @OA\Property(property="change_value", type="number", format="float", example=0.07, description="Difference between current and previous"),
     *                      @OA\Property(property="change_type", type="string", example="increase", enum={"increase", "decrease", "no_change"}, description="Type of change"),
     *                      @OA\Property(property="label", type="string", example="positive", enum={"positive", "neutral", "negative"}, description="Current period sentiment label"),
     *                      @OA\Property(property="previous_label", type="string", example="neutral", enum={"positive", "neutral", "negative"}, description="Previous period sentiment label")
     *                  )
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
     *          response=403,
     *          description="Forbidden - No business found for authenticated user",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="No business found for the authenticated user")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed - Invalid period",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid period parameter")
     *          )
     *      )
     * )
     */
    public function getBranchOverview(Request $request)
    {
        $user = $request->user();
        $businessId = $user->business_id;

        if (!$businessId) {
            throw new AuthorizationException('No business found for the authenticated user');
        }


        // Get branch IDs
        $branchIds = Branch::where('business_id', $businessId)
            ->branchGlobalFilters()
            ->pluck('id');

        // ==================== TOTAL BRANCHES ====================
        $currentBranchCount = Branch::where('business_id', $businessId)
            ->branchGlobalFilters()
            ->filterByDateRange()
            ->count();

        $previousBranchCount = Branch::where('business_id', $businessId)
            ->branchGlobalFilters()
            ->filterByDateRange(true)
            ->count();

        $branchCountChange = $currentBranchCount - $previousBranchCount;
        $branchCountChangeType = $branchCountChange > 0 ? 'increase' : ($branchCountChange < 0 ? 'decrease' : 'no_change');

        // ==================== REVIEWS COLLECTIONS ====================
        $currentReviews = ReviewNew::whereIn('branch_id', $branchIds)
            ->globalReviewFilters(0)
            ->filterByDateRange()
            ->withCalculatedRating()
            ->get();

        $previousReviews = ReviewNew::whereIn('branch_id', $branchIds)
            ->globalReviewFilters(0)
            ->filterByDateRange(true)
            ->withCalculatedRating()
            ->get();

        // ==================== AVERAGE RATING ====================
        $currentAvgRating = $currentReviews->avg('calculated_rating') ?? 0;
        $previousAvgRating = $previousReviews->avg('calculated_rating') ?? 0;

        $avgRatingChange = round($currentAvgRating - $previousAvgRating, 2);
        $avgRatingChangeType = $avgRatingChange > 0 ? 'increase' : ($avgRatingChange < 0 ? 'decrease' : 'no_change');

        // ==================== SENTIMENT SCORE ====================
        $currentSentimentScore = $currentReviews->avg('sentiment_score') ?? 0;
        $previousSentimentScore = $previousReviews->avg('sentiment_score') ?? 0;

        $sentimentScoreChange = round($currentSentimentScore - $previousSentimentScore, 2);
        $sentimentScoreChangeType = $sentimentScoreChange > 0 ? 'increase' : ($sentimentScoreChange < 0 ? 'decrease' : 'no_change');

        // ==================== SENTIMENT LABELS (Count-based) ====================
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        // Current labels
        $posCount = $currentReviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $negCount = $currentReviews->where('sentiment_score', '<', $negativeThreshold)->count();
        $neuCount = $currentReviews->count() - $posCount - $negCount;
        $currentSentimentLabel = RuleEngineService::determineAggregatedLabel($posCount, $neuCount, $negCount);

        // Previous labels
        $prevPosCount = $previousReviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $prevNegCount = $previousReviews->where('sentiment_score', '<', $negativeThreshold)->count();
        $prevNeuCount = $previousReviews->count() - $prevPosCount - $prevNegCount;
        $previousSentimentLabel = RuleEngineService::determineAggregatedLabel($prevPosCount, $prevNeuCount, $prevNegCount);

        return response()->json([
            'success' => true,
            'message' => 'Branch overview retrieved successfully',
            'data' => [
                'total_branches' => [
                    'value' => $currentBranchCount,
                    'previous_value' => $previousBranchCount,
                    'change_value' => $branchCountChange,
                    'change_type' => $branchCountChangeType,
                ],
                'avg_rating' => [
                    'value' => round($currentAvgRating, 2),
                    'previous_value' => round($previousAvgRating, 2),
                    'change_value' => $avgRatingChange,
                    'change_type' => $avgRatingChangeType,
                ],
                'overall_sentiment' => [
                    'value' => round($currentSentimentScore, 2),
                    'previous_value' => round($previousSentimentScore, 2),
                    'change_value' => $sentimentScoreChange,
                    'change_type' => $sentimentScoreChangeType,
                    'label' => $currentSentimentLabel,
                    'previous_label' => $previousSentimentLabel,
                ],
            ],
        ]);
    }
    /**
     * @OA\Post(
     *      path="/v1.0/branches",
     *      operationId="createBranch",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Create a new branch",
     *      description="Create a new branch for a business. Only business owners can perform this action.",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"business_id", "name"},
     *              @OA\Property(property="business_id", type="integer", example=1, description="ID of the business"),
     *              @OA\Property(property="name", type="string", example="Main Branch", description="Name of the branch"),
     *              @OA\Property(property="manager_id", type="integer", nullable=true, example=5, description="ID of the branch manager (user with branch_manager role)"),
     *              @OA\Property(property="address", type="string", example="123 Main St", description="Address of the branch"),              @OA\Property(property="street", type="string", example="Main Street", description="Street address"),
     *              @OA\Property(property="door_no", type="string", example="123", description="Door number"),
     *              @OA\Property(property="city", type="string", example="New York", description="City"),
     *              @OA\Property(property="country", type="string", example="USA", description="Country"),
     *              @OA\Property(property="postcode", type="string", example="10001", description="Postcode"),
     *              @OA\Property(property="phone", type="string", example="+1234567890", description="Phone number of the branch"),
     *              @OA\Property(property="email", type="string", example="branch@example.com", description="Email of the branch"),
     *              @OA\Property(property="is_active", type="boolean", example=true, description="Whether the branch is active"),
     *              @OA\Property(property="is_default", type="boolean", example=false, description="Whether this is the default branch"),
     *              @OA\Property(property="is_geo_enabled", type="boolean", example=false, description="Whether geolocation is enabled for the branch"),
     *              @OA\Property(property="branch_code", type="string", example="BR001", description="Unique code for the branch"),
     *              @OA\Property(property="lat", type="string", format="float", example="40.7128", description="lat coordinate of the branch"),
     *              @OA\Property(property="long", type="string", format="float", example="-74.0060", description="long coordinate of the branch")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="Branch created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch created successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="Main Branch"),
     *                  @OA\Property(property="manager_id", type="integer", nullable=true, example=5),
     *                  @OA\Property(property="address", type="string", example="123 Main St"),
     *                  @OA\Property(property="street", type="string", example="Main Street"),
     *                  @OA\Property(property="door_no", type="string", example="123"),
     *                  @OA\Property(property="city", type="string", example="New York"),
     *                  @OA\Property(property="country", type="string", example="USA"),
     *                  @OA\Property(property="postcode", type="string", example="10001"),
     *                  @OA\Property(property="phone", type="string", example="+1234567890"),
     *                  @OA\Property(property="email", type="string", example="branch@yopmail.com"),
     *                  @OA\Property(property="is_active", type="boolean", example=true),
     *                  @OA\Property(property="is_default", type="boolean", example=false),
     *                  @OA\Property(property="is_geo_enabled", type="boolean", example=false),
     *                  @OA\Property(property="branch_code", type="string", example="BR001"),
     *                  @OA\Property(property="lat", type="string", format="float", example="40.7128"),
     *                  @OA\Property(property="long", type="string", format="float", example="-74.0060"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
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
     *          response=403,
     *          description="Forbidden - Not business owner or super admin",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to create branches for this business")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */
    public function createBranch(BranchRequest $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can create branches.');
            }

            return DB::transaction(function () use ($request, $user) {
                $validatedData = $request->validated();

                // ==================== BUSINESS OWNERSHIP VALIDATION ====================
                if ($validatedData['business_id'] !== $user->business_id) {
                    throw new AccessDeniedHttpException('You can only create branches for your own business.');
                }

                Business::findOrFail($validatedData['business_id']);

                // ==================== CREATE BRANCH ====================
                $branch = Branch::create($validatedData);

                return response()->json([
                    'success' => true,
                    'message' => 'Branch created successfully',
                    'data' => $branch
                ], 201);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }



    /**
     * @OA\Get(
     *      path="/v1.0/branches/{id}",
     *      operationId="getBranchById",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get a specific branch by ID",
     *      description="Retrieve details of a specific branch. Only business owners can access branches of their businesses.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="Branch ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Branch retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="Main Branch"),
     *                  @OA\Property(property="manager_id", type="integer", nullable=true, example=5),
     *                  @OA\Property(property="address", type="string", example="123 Main St"),
     *                  @OA\Property(property="street", type="string", example="Main Street"),
     *                  @OA\Property(property="door_no", type="string", example="123"),
     *                  @OA\Property(property="city", type="string", example="New York"),
     *                  @OA\Property(property="country", type="string", example="USA"),
     *                  @OA\Property(property="postcode", type="string", example="10001"),
     *                  @OA\Property(property="phone", type="string", example="+1234567890"),
     *                  @OA\Property(property="email", type="string", example="branch@yopmail.com"),
     *                  @OA\Property(property="is_active", type="boolean", example=true),
     *                  @OA\Property(property="is_default", type="boolean", example=false),
     *                  @OA\Property(property="is_geo_enabled", type="boolean", example=false),
     *                  @OA\Property(property="branch_code", type="string", example="BR001"),
     *                  @OA\Property(property="lat", type="string", format="float", example="40.7128"),
     *                  @OA\Property(property="long", type="string", format="float", example="-74.0060"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time"),
     *                  @OA\Property(
     *                      property="business",
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="Name", type="string", example="Business Name")
     *                  )
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
     *          response=403,
     *          description="Forbidden - Not business owner or super admin",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to view this branch")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Branch not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Branch not found")
     *          )
     *      )
     * )
     */
    public function getBranchById($id)
    {
        $branch = Branch::with('business')->findOrFail($id);

        // Check ownership
        $user = auth('api')->user();
        if ($branch->business->OwnerID != $user->id) {
            throw new AccessDeniedHttpException('This branch does not belongs to your business');
        }

        return response()->json([
            'success' => true,
            'message' => 'Branch retrieved successfully',
            'data' => $branch
        ]);
    }

    /**
     * @OA\Patch(
     *      path="/v1.0/branches/{id}",
     *      operationId="updateBranch",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Update a specific branch",
     *      description="Update details of a specific branch. Only business owners or super admins can update branches of their businesses.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="Branch ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="id", type="integer", example=1, description="Branch ID (required for PATCH/PUT)"),
     *              @OA\Property(property="business_id", type="integer", example=1, description="ID of the business"),
     *              @OA\Property(property="name", type="string", example="Updated Branch", description="Name of the branch"),
     *              @OA\Property(property="manager_id", type="integer", nullable=true, example=5, description="ID of the branch manager (user with branch_manager role)"),
     *              @OA\Property(property="address", type="string", example="456 Updated St", description="Address of the branch"),              @OA\Property(property="street", type="string", example="Updated Street", description="Street address"),
     *              @OA\Property(property="door_no", type="string", example="456", description="Door number"),
     *              @OA\Property(property="city", type="string", example="Updated City", description="City"),
     *              @OA\Property(property="country", type="string", example="Updated Country", description="Country"),
     *              @OA\Property(property="postcode", type="string", example="20002", description="Postcode"),
     *              @OA\Property(property="phone", type="string", example="+0987654321", description="Phone number of the branch"),
     *              @OA\Property(property="email", type="string", example="updated@yopmail.com", description="Email of the branch"),
     *              @OA\Property(property="is_active", type="boolean", example=true, description="Whether the branch is active"),
     *              @OA\Property(property="is_default", type="boolean", example=false, description="Whether this is the default branch"),
     *              @OA\Property(property="is_geo_enabled", type="boolean", example=false, description="Whether geolocation is enabled for the branch"),
     *              @OA\Property(property="branch_code", type="string", example="BR001", description="Unique code for the branch"),
     *              @OA\Property(property="lat", type="string", format="float", example="40.7128", description="lat coordinate of the branch"),
     *              @OA\Property(property="long", type="string", format="float", example="-74.0060", description="long coordinate of the branch")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Branch updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch updated successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="Updated Branch"),
     *                  @OA\Property(property="manager_id", type="integer", nullable=true, example=5),
     *                  @OA\Property(property="address", type="string", example="456 Updated St"),
     *                  @OA\Property(property="street", type="string", example="Updated Street"),
     *                  @OA\Property(property="door_no", type="string", example="456"),
     *                  @OA\Property(property="city", type="string", example="Updated City"),
     *                  @OA\Property(property="country", type="string", example="Updated Country"),
     *                  @OA\Property(property="postcode", type="string", example="20002"),
     *                  @OA\Property(property="phone", type="string", example="+0987654321"),
     *                  @OA\Property(property="email", type="string", example="updated@yopmail.com"),
     *                  @OA\Property(property="is_active", type="boolean", example=true),
     *                  @OA\Property(property="is_default", type="boolean", example=false),
     *                  @OA\Property(property="is_geo_enabled", type="boolean", example=false),
     *                  @OA\Property(property="branch_code", type="string", example="BR001"),
     *                  @OA\Property(property="lat", type="string", format="float", example="40.7128"),
     *                  @OA\Property(property="long", type="string", format="float", example="-74.0060"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
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
     *          response=403,
     *          description="Forbidden - Not business owner, super admin, or attempting to update default branch",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Cannot update default branch")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Branch not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Branch not found")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */
    public function updateBranch(BranchRequest $request, $id)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can update branches.');
            }

            return DB::transaction(function () use ($request, $id, $user) {
                $validatedData = $request->validated();

                // ==================== FIND BRANCH ====================
                $branch = Branch::findOrFail($id);

                // ==================== OWNERSHIP VALIDATION ====================
                if ($branch->business_id !== $user->business_id) {
                    throw new AccessDeniedHttpException('This branch does not belong to your business.');
                }

                // ==================== PREVENT UPDATING DEFAULT BRANCH ====================
                if ($branch->is_default) {
                    throw new AccessDeniedHttpException('Cannot update default branch.');
                }

                // ==================== UPDATE BRANCH ====================
                $branch->update($validatedData);

                return response()->json([
                    'success' => true,
                    'message' => 'Branch updated successfully',
                    'data' => $branch
                ]);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Delete(
     *      path="/v1.0/branches/{id}",
     *      operationId="deleteBranches",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Delete a specific branch",
     *      description="Delete a specific branch. Only business owners or super admins can delete branches of their businesses.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="Branch ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Branch deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch deleted successfully")
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
     *          response=403,
     *          description="Forbidden - Not business owner, super admin, or attempting to delete default branch",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Cannot delete default branch")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Branch not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Branch not found")
     *          )
     *      )
     * )
     */
    public function deleteBranches($id)
    {
        try {
            $user = auth('api')->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can delete branches.');
            }

            // ==================== FIND BRANCH ====================
            $branch = Branch::findOrFail($id);

            // ==================== OWNERSHIP VALIDATION ====================
            if ($branch->business_id != $user->business_id) {
                throw new AccessDeniedHttpException('This branch does not belong to your business.');
            }

            // ==================== PREVENT DELETING DEFAULT BRANCH ====================
            if ($branch->is_default) {
                throw new AccessDeniedHttpException('Cannot delete default branch.');
            }

            // ==================== DELETE BRANCH ====================
            $branch->delete();

            return response()->json([
                'success' => true,
                'message' => 'Branch deleted successfully'
            ]);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Patch(
     *   path="/v1.0/branches/{id}/toggle-active",
     *   operationId="toggleBranchActive",
     *   tags={"branches"},
     *   security={{"bearerAuth":{}}},
     *   summary="Toggle branch active status",
     *   description="Toggle the active status of a specific branch.",
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Branch ID",
     *     @OA\Schema(type="integer")
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Branch status toggled successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Branch activated successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="business_id", type="integer", example=1),
     *         @OA\Property(property="name", type="string", example="Main Branch"),                  @OA\Property(property="manager_id", type="integer", nullable=true, example=5),     *         @OA\Property(property="address", type="string", example="123 Main St"),
     *         @OA\Property(property="street", type="string", example="Main Street"),
     *         @OA\Property(property="door_no", type="string", example="123"),
     *         @OA\Property(property="city", type="string", example="New York"),
     *         @OA\Property(property="country", type="string", example="USA"),
     *         @OA\Property(property="postcode", type="string", example="10001"),
     *         @OA\Property(property="phone", type="string", example="+1234567890"),
     *         @OA\Property(property="email", type="string", example="branch@yopmail.com"),
     *         @OA\Property(property="is_active", type="boolean", example=true),
     *         @OA\Property(property="is_default", type="boolean", example=false),
     *         @OA\Property(property="is_geo_enabled", type="boolean", example=false),
     *         @OA\Property(property="branch_code", type="string", example="BR001"),
     *         @OA\Property(property="lat", type="number", format="float", example=40.7128),
     *         @OA\Property(property="long", type="number", format="float", example=-74.0060),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Branch not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Branch not found")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=403,
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="You do not own this branch")
     *     )
     *   )
     * )
     */

    public function toggleBranchActive($id)
    {
        try {
            $user = auth('api')->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can toggle branch status.');
            }

            // ==================== FIND BRANCH ====================
            $branch = Branch::with('manager')->findOrFail($id);

            // ==================== OWNERSHIP VALIDATION ====================
            if ($branch->business_id !== $user->business_id) {
                throw new AccessDeniedHttpException('This branch does not belong to your business.');
            }

            // ==================== TOGGLE STATUS ====================
            $branch->is_active = !$branch->is_active;
            $branch->save();

            $message = $branch->is_active ? 'Branch activated successfully' : 'Branch deactivated successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $branch
            ]);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get branch metrics with comparison
     *
     * @OA\Get(
     *      path="/v1.0/branches/{branchId}/metrics",
     *      operationId="getBranchMetrics",
     *      tags={"branch_management"},
     *      summary="Get branch metrics with period comparison",
     *      description="Returns branch metrics including ratings, sentiment, staff performance with comparison to previous period",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="branchId",
     *          in="path",
     *          required=true,
     *          description="Branch ID",
     *          @OA\Schema(type="integer", example=1)
     *      ),
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          description="Time period for metrics",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              enum={"last_7_days", "last_30_days", "last_90_days", "this_week", "last_week", "this_month", "last_month", "this_quarter", "last_quarter", "this_year", "last_year"},
     *              default="last_30_days"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Branch metrics retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch metrics retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=404, description="Branch not found")
     * )
     */
    public function branchMetric($branchId, Request $request)
    {
        $user = $request->user();
        $businessId = $user->business_id;

        // Check permissions
        if (!$user->hasRole('branch_manager') && !$user->hasRole('business_owner')) {
            throw new AuthorizationException('You do not have permission to view this branch metric');
        }

        // ==================== AUTHORIZATION ====================
        $branch = Branch::with('manager')->findOrFail($branchId);

        // Ensure branch belongs to user's business
        if ($branch->business_id !== $businessId) {
            throw new AuthorizationException('The Branch does not belong to your business');
        }

        // If user is a branch_manager (not business_owner), ensure they manage this specific branch
        if ($user->hasRole('branch_manager') && !$user->hasRole('business_owner')) {
            if ($branch->manager_id !== $user->id) {
                throw new AuthorizationException('You are not the manager of this branch');
            }
        }

        // Validate period and get date range
        $dateRange = $this->branchService->validateAndGetDateRange($request->get('period', 'last_30_days'));

        // Get metrics using BranchService with named arguments
        $metrics = $this->branchService->getBranchMetricsWithComparison(
            branchId: $branchId,
            dateRange: $dateRange,
            user: $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Branch metrics retrieved successfully',
            'data' => $metrics
        ], 200);
    }

    /**
     * Get AI-generated insights for a branch
     *
     * @OA\Get(
     *      path="/v1.0/branches/{branchId}/ai-insights",
     *      operationId="getBranchAiInsights",
     *      tags={"branch_management"},
     *      summary="Get AI-generated insights for branch reviews",
     *      description="Returns AI-generated summary, sentiment breakdown, and key trends based on branch reviews for the specified period",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="branchId",
     *          in="path",
     *          required=true,
     *          description="Branch ID",
     *          @OA\Schema(type="integer", example=1)
     *      ),
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          description="Time period for analysis",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              enum={"last_7_days", "last_30_days", "last_90_days", "this_week", "last_week", "this_month", "last_month", "this_quarter", "last_quarter", "this_year", "last_year"},
     *              default="last_30_days"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="AI insights generated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="AI insights generated successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(
     *                      property="summary",
     *                      type="string",
     *                      example="Overall customer sentiment is positive with 75% positive reviews. Key strengths include excellent customer service and product quality. Areas for improvement include delivery times and packaging."
     *                  ),
     *                  @OA\Property(
     *                      property="sentiment_breakdown",
     *                      type="object",
     *                      @OA\Property(property="positive", type="integer", example=75),
     *                      @OA\Property(property="neutral", type="integer", example=15),
     *                      @OA\Property(property="negative", type="integer", example=10)
     *                  ),
     *                  @OA\Property(
     *                      property="key_trends",
     *                      type="array",
     *                      @OA\Items(
     *                          type="object",
     *                          @OA\Property(property="topic", type="string", example="customer service"),
     *                          @OA\Property(property="sentiment", type="string", example="positive"),
     *                          @OA\Property(property="mentions", type="integer", example=45)
     *                      )
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Unauthorized access to branch",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthorized access to branch")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Branch not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Branch not found")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Invalid period parameter",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid period parameter"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Server error while generating insights",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Failed to generate AI insights")
     *          )
     *      )
     * )
     */
    public function branchAiInsights($branchId, Request $request)
    {
        try {
            // ==================== GET USER & BUSINESS ====================
            $user = $request->user();
            $businessId = $user->business_id;

            // Check permissions (branch manager or business owner)
            if (!$user->hasRole('branch_manager') && !$user->hasRole('business_owner')) {
                throw new AuthorizationException('You do not have permission to view AI insights for this branch');
            }

            // ==================== AUTHORIZATION ====================
            $branch = Branch::with('manager')->findOrFail($branchId);

            // Ensure branch belongs to user's business
            if ($branch->business_id !== $businessId) {
                throw new AuthorizationException('The Branch does not belong to your business');
            }


            // If user is a branch_manager (not business_owner), ensure they manage this specific branch
            if ($user->hasRole('branch_manager') && !$user->hasRole('business_owner')) {
                if ($branch->manager_id !== $user->id) {
                    throw new AuthorizationException('You are not the manager of this branch');
                }
            }

            // ==================== VALIDATE & GET DATE RANGE ====================
            $dateRange = $this->branchService->validateAndGetDateRange(
                $request->get('period', 'last_30_days')
            );

            // ==================== GET REVIEWS ====================
            $reviews = $this->reviewService->getCurrentPeriodReviews(
                businessId: $businessId,
                branchId: $branchId,
                dateRange: $dateRange === 'all_time' ? null : $dateRange,
            );

            // ==================== GENERATE AI INSIGHTS ====================
            // Use existing AIProcessor instead of duplicating logic
            $insights = AIProcessorService::generateAiInsights($reviews);

            // ==================== RETURN RESPONSE ====================
            return response()->json([
                'success' => true,
                'message' => 'AI insights generated successfully',
                'data' => $insights
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get staff performance for a specific branch
     *
     * @OA\Get(
     *     path="/v1.0/branches/{branchId}/staff-performance",
     *     tags={"branch_management"},
     *     summary="Get staff performance metrics for a branch",
     *     description="Retrieves detailed performance metrics for staff members in a specific branch, including ratings, sentiment scores, and trends",
     *     operationId="getBranchStaffPerformance",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="branchId",
     *         in="path",
     *         required=true,
     *         description="ID of the branch",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Time period for analysis (last_7_days, last_30_days, last_90_days, this_month, last_month, custom)",
     *         @OA\Schema(type="string", enum={"last_7_days", "last_30_days", "last_90_days", "this_month", "last_month", "custom"}, example="last_30_days")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Maximum number of staff members to return",
     *         @OA\Schema(type="integer", minimum=1, maximum=50, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Staff performance retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Staff performance generated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="staff_id", type="integer", example=42),
     *                     @OA\Property(property="staff_name", type="string", example="John Doe"),
     *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
     *                     @OA\Property(property="reviews_count", type="integer", example=25),
     *                     @OA\Property(property="positive_percentage", type="integer", example=85),
     *                     @OA\Property(property="evaluation", type="string", example="Top Performer"),
     *                     @OA\Property(property="rating_trend", type="string", example="improving"),
     *                     @OA\Property(property="last_review_date", type="string", example="2 days ago")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authorization error - User doesn't have permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view staff performance for this branch"),
     *             @OA\Property(property="error", type="string", example="authorization_error")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Branch not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Branch not found"),
     *             @OA\Property(property="error", type="string", example="not_found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid period parameter"),
     *             @OA\Property(property="error", type="string", example="validation_error")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred while retrieving staff performance"),
     *             @OA\Property(property="error", type="string", example="server_error")
     *         )
     *     )
     * )
     */
    public function getBranchStaffPerformance($branchId, Request $request)
    {

        // ==================== GET USER & BUSINESS ====================
        $user = $request->user();
        $businessId = $user->business_id;

        // Check permissions (branch manager or business owner)
        if (!$user->hasRole('branch_manager') && !$user->hasRole('business_owner')) {
            throw new AuthorizationException('You do not have permission to view staff performance for this branch');
        }

        // ==================== AUTHORIZATION ====================
        $branch = Branch::with('manager')->findOrFail($branchId);

        // Ensure branch belongs to user's business
        if ($branch->business_id !== $businessId) {
            throw new AuthorizationException('The Branch does not belong to your business');
        }


        // If user is a branch_manager (not business_owner), ensure they manage this specific branch
        if ($user->hasRole('branch_manager') && !$user->hasRole('business_owner')) {
            if ($branch->manager_id !== $user->id) {
                throw new AuthorizationException('You are not the manager of this branch');
            }
        }

        // ==================== VALIDATE & GET DATE RANGE ====================
        $dateRange = $this->branchService->validateAndGetDateRange(
            $request->get('period', 'last_30_days')
        );

        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        $staffPerformance = $this->aiProcessorService->getStaffPerformance(
            branchId: $branchId,
            businessId: $businessId,
            startDate: $startDate,
            endDate: $endDate,
            limit: $request->get('limit', 10)
        );

        // ==================== RETURN RESPONSE ====================
        return response()->json([
            'success' => true,
            'message' => 'Staff performance generated successfully',
            'data' => $staffPerformance
        ], 200);
    }

    /**
     * Get recent reviews for a specific branch
     *
     * @OA\Get(
     *     path="/v1.0/branches/{branchId}/recent-reviews",
     *     tags={"branch_management"},
     *     summary="Get recent reviews for a branch",
     *     description="Retrieves the most recent reviews for a specific branch, sorted by creation date",
     *     operationId="getBranchRecentReviews",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="branchId",
     *         in="path",
     *         required=true,
     *         description="ID of the branch",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Time period for filtering reviews (last_7_days, last_30_days, last_90_days, this_month, last_month, all_time, custom)",
     *         @OA\Schema(type="string", enum={"last_7_days", "last_30_days", "last_90_days", "this_month", "last_month", "all_time", "custom"}, example="last_30_days")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Maximum number of recent reviews to return",
     *         @OA\Schema(type="integer", minimum=1, maximum=50, example=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recent reviews retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recent reviews generated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=123),
     *                     @OA\Property(property="customer_name", type="string", example="John Doe"),
     *                     @OA\Property(property="rating", type="number", format="float", example=4.5),
     *                     @OA\Property(property="comment", type="string", example="Great service and friendly staff!"),
     *                     @OA\Property(property="sentiment_label", type="string", example="positive"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T15:30:00Z"),
     *                     @OA\Property(property="time_ago", type="string", example="2 hours ago")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authorization error - User doesn't have permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view reviews for this branch"),
     *             @OA\Property(property="error", type="string", example="authorization_error")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Branch not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Branch not found"),
     *             @OA\Property(property="error", type="string", example="not_found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid period parameter"),
     *             @OA\Property(property="error", type="string", example="validation_error")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred while retrieving recent reviews"),
     *             @OA\Property(property="error", type="string", example="server_error")
     *         )
     *     )
     * )
     */
    public function branchRecentReviews($branchId, Request $request)
    {
        // ==================== GET USER & BUSINESS ====================
        $user = $request->user();
        $businessId = $user->business_id;

        // Check permissions (branch manager or business owner)
        if (!$user->hasRole('branch_manager') && !$user->hasRole('business_owner')) {
            throw new AuthorizationException('You do not have permission to view reviews for this branch');
        }

        // ==================== AUTHORIZATION ====================
        $branch = Branch::with('manager')->findOrFail($branchId);

        // Ensure branch belongs to user's business
        if ($branch->business_id !== $businessId) {
            throw new AuthorizationException('The Branch does not belong to your business');
        }

        // If user is a branch_manager (not business_owner), ensure they manage this specific branch
        if ($user->hasRole('branch_manager') && !$user->hasRole('business_owner')) {
            if ($branch->manager_id !== $user->id) {
                throw new AuthorizationException('You are not the manager of this branch');
            }
        }

        // ==================== VALIDATE & GET DATE RANGE ====================
        $dateRange = $this->branchService->validateAndGetDateRange(
            $request->get('period', 'last_30_days')
        );


        // ==================== GET REVIEWS ====================
        $reviews = $this->reviewService->getCurrentPeriodReviews(
            businessId: $businessId,
            branchId: $branchId,
            dateRange: $dateRange === 'all_time' ? null : $dateRange,
        );

        // ==================== GET RECENT REVIEWS ====================
        $recentReviews = $this->recentReviewService->getRecentReviews(
            reviews: $reviews,
            limit: $request->get('limit', 5)
        );

        // ==================== RETURN RESPONSE ====================
        return response()->json([
            'success' => true,
            'message' => 'Recent reviews generated successfully',
            'data' => $recentReviews
        ], 200);
    }


    /**
     * Get AI-generated recommendations for a specific branch
     *
     * @OA\Get(
     *     path="/v1.0/branches/{branchId}/recommendations",
     *     tags={"branch_management"},
     *     summary="Get AI recommendations for a branch",
     *     description="Retrieves AI-generated actionable recommendations for improving branch performance based on review analysis",
     *     operationId="getBranchRecommendations",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="branchId",
     *         in="path",
     *         required=true,
     *         description="ID of the branch",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Time period for analysis (last_7_days, last_30_days, last_90_days, this_month, last_month, all_time, custom)",
     *         @OA\Schema(type="string", enum={"last_7_days", "last_30_days", "last_90_days", "this_month", "last_month", "all_time", "custom"}, example="last_30_days")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Branch recommendations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Branch recommendations generated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="type", type="string", example="Action", description="Recommendation type: Action, Strength, Weak Area, Info"),
     *                     @OA\Property(property="title", type="string", example="Optimize Service Flow"),
     *                     @OA\Property(property="description", type="string", example="Review staffing schedules during peak hours and implement queue management."),
     *                     @OA\Property(property="priority", type="string", example="high", description="Priority level: high, medium, low"),
     *                     @OA\Property(property="evidence_count", type="integer", example=5, description="Number of reviews supporting this recommendation")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authorization error - User doesn't have permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to view recommendations for this branch"),
     *             @OA\Property(property="error", type="string", example="authorization_error")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Branch not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Branch not found"),
     *             @OA\Property(property="error", type="string", example="not_found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid period parameter"),
     *             @OA\Property(property="error", type="string", example="validation_error")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred while generating recommendations"),
     *             @OA\Property(property="error", type="string", example="server_error")
     *         )
     *     )
     * )
     */
    public function branchBranchRecommendations($branchId, Request $request)
    {
        // ==================== GET USER & BUSINESS ====================
        $user = $request->user();
        $businessId = $user->business_id;

        // Check permissions (branch manager or business owner)
        if (!$user->hasRole('branch_manager') && !$user->hasRole('business_owner')) {
            throw new AuthorizationException('You do not have permission to view recommendations for this branch');
        }

        // ==================== AUTHORIZATION ====================
        $branch = Branch::with('manager')->findOrFail($branchId);

        // Ensure branch belongs to user's business
        if ($branch->business_id !== $businessId) {
            throw new AuthorizationException('The Branch does not belong to your business');
        }

        // If user is a branch_manager (not business_owner), ensure they manage this specific branch
        if ($user->hasRole('branch_manager') && !$user->hasRole('business_owner')) {
            if ($branch->manager_id !== $user->id) {
                throw new AuthorizationException('You are not the manager of this branch');
            }
        }

        // ==================== VALIDATE & GET DATE RANGE ====================
        $dateRange = $this->branchService->validateAndGetDateRange(
            $request->get('period', 'last_30_days')
        );


        // ==================== GET REVIEWS ====================
        $reviews = $this->reviewService->getCurrentPeriodReviews(
            businessId: $businessId,
            branchId: $branchId,
            dateRange: $dateRange === 'all_time' ? null : $dateRange,
        );

        // ==================== GET RECENT REVIEWS ====================
        $branchRecommendations = $this->aiProcessorService->generateBranchRecommendationsFromRuleEngine(
            reviews: $reviews,
            businessId: $businessId,
            branchId: $branchId,
        );

        // ==================== RETURN RESPONSE ====================
        return response()->json([
            'success' => true,
            'message' => 'Branch recommendations generated successfully',
            'data' => $branchRecommendations
        ], 200);
    }
}
