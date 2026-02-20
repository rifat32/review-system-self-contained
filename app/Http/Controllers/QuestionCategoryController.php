<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuestionCategoryRequest;
use App\Http\Utils\ErrorUtil;
use App\Models\Business;
use App\Models\QuestionCategory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuestionCategoryController extends Controller
{
    use ErrorUtil;

    /**
     * @OA\Post(
     *      path="/v1.0/question-categories",
     *      operationId="createQuestionCategory",
     *      tags={"question_category_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to create question category",
     *      description="This method is to create question category",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"title"},
     *    @OA\Property(property="title", type="string", format="string", example="Staff"),
     *    @OA\Property(property="description", type="string", format="string", example="Staff-related questions"),
     *    @OA\Property(property="parent_question_category_id", type="integer", format="integer", example=null),
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
     *          description="Unprocessable Content",
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

    public function createQuestionCategory(QuestionCategoryRequest $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('superadmin') && !$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('You do not have permission to create question categories.');
            }

            return DB::transaction(function () use ($request, $user) {
                $payload_data = $request->validated();

                // ==================== HANDLE ROLE-BASED CREATION ====================
                if ($user->hasRole('superadmin')) {
                    // Superadmin creates ONLY default categories
                    $payload_data['is_default'] = true;
                    $payload_data['business_id'] = null;
                } else {
                    // Business owner creates business-specific categories
                    if (!$user->business_id) {
                        throw new AccessDeniedHttpException('No business associated with your account.');
                    }

                    $payload_data['is_default'] = false;
                    $payload_data['business_id'] = $user->business_id;
                }

                // ==================== CREATE CATEGORY ====================
                $payload_data['created_by'] = $user->id;
                $questionCategory = QuestionCategory::create($payload_data);

                return response()->json([
                    "success" => true,
                    "message" => "Question category created successfully",
                    "data" => $questionCategory
                ], 201);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Get(
     *      path="/v1.0/question-categories",
     *      operationId="getAllQuestionCategories",
     *      tags={"question_category_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get all question categories",
     *      description="This method is to get all question categories",
     *
     *      @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         example=1
     *      ),
     *      @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         example=20
     *      ),
     *      @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         example=1
     *      ),
     *      @OA\Parameter(
     *         name="is_default",
     *         in="query",
     *         description="Filter by default status",
     *         required=false,
     *         example=0
     *      ),
     *      @OA\Parameter(
     *         name="parent_id",
     *         in="query",
     *         description="Filter by parent category ID",
     *         required=false,
     *         example=1
     *      ),
     *      @OA\Parameter(
     *         name="created_by",
     *         in="query",
     *         description="Filter by creator user ID",
     *         required=false,
     *         example=1
     *      ),
     *      @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in title and description",
     *         required=false,
     *         example="staff"
     *      ),
     *      @OA\Parameter(
     *         name="has_children",
     *         in="query",
     *         description="Filter categories with/without children",
     *         required=false,
     *         example=1
     *      ),
     *      @OA\Parameter(
     *         name="exclude_parent",
     *         in="query",
     *         description="Filter categories which does not have parent",
     *         required=false,
     *         example=1
     *      ),
     *      @OA\Parameter(
     *         name="has_questions",
     *         in="query",
     *         description="Filter categories with/without questions",
     *         required=false,
     *         example=0
     *      ),
     *      @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field (title, created_at, updated_at, is_active, is_default)",
     *         required=false,
     *         example="title"
     *      ),
     *      @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc, desc)",
     *         required=false,
     *         example="asc"
     *      ),
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
     *          description="Unprocessable Content",
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

    public function getAllQuestionCategories(Request $request)
    {
        try {

            $query = QuestionCategory::with([
                'parent',
                'children' => function ($q) {
                    $q->where([
                        "question_categories.business_id" => auth()->user()->business_id,
                    ]);
                },

            ])->filters(auth()->user()->business_id);

            $questionCategories = retrieve_data($query);

            return response()->json([
                "success" => true,
                "message" => "Question categories retrieved successfully",
                "data" => $questionCategories
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Get(
     *      path="/v1.0/question-categories/{id}",
     *      operationId="getQuestionCategoryById",
     *      tags={"question_category_management"},
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
     *      summary="This method is to get question category by id",
     *      description="This method is to get question category by id",
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
     *          description="Unprocessable Content",
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

    public function getQuestionCategoryById($id, Request $request)
    {
        try {
            $questionCategory = QuestionCategory::with(['parent', 'children', 'questions'])
                ->findOrFail($id);


            return response()->json([
                "success" => true,
                "message" => "Question category retrieved successfully",
                "data" => $questionCategory
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Patch(
     *      path="/v1.0/question-categories/{id}",
     *      operationId="updateQuestionCategory",
     *      tags={"question_category_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update question category",
     *      description="This method is to update question category",
     *
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Question category ID",
     *         required=true,
     *         example=1
     *      ),
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"title"},
     *    @OA\Property(property="title", type="string", format="string", example="Updated Staff Category"),
     *    @OA\Property(property="description", type="string", format="string", example="Updated staff-related questions"),
     *    @OA\Property(property="parent_question_category_id", type="integer", format="integer", example=null),
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
     *          description="Unprocessable Content",
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

    public function updateQuestionCategory(QuestionCategoryRequest $request, $id)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('superadmin') && !$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('You do not have permission to update question categories.');
            }

            return DB::transaction(function () use ($request, $id, $user) {
                $questionCategory = QuestionCategory::findOrFail($id);
                $payload_data = $request->validated();

                // ==================== BUSINESS VALIDATION ====================
                if ($user->hasRole('superadmin')) {
                    // Superadmin can ONLY update default categories
                    if (!$questionCategory->is_default) {
                        throw new AccessDeniedHttpException('Superadmin can only update default question categories.');
                    }
                    $payload_data['business_id'] = null;
                } else {
                    // Business owner can ONLY update their own business categories (NOT default)
                    if ($questionCategory->is_default) {
                        throw new AccessDeniedHttpException('Cannot update default question categories.');
                    }

                    if ($questionCategory->business_id !== $user->business_id) {
                        throw new AccessDeniedHttpException('Question category ' . $questionCategory->id . ' belongs to another business.');
                    }
                }

                // ==================== UPDATE CATEGORY ====================
                $questionCategory->update($payload_data);
                $questionCategory->load(['parent', 'children']);

                return response()->json([
                    "success" => true,
                    "message" => "Question category updated successfully",
                    "data" => $questionCategory
                ], 200);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Patch(
     *      path="/v1.0/question-categories/toggle",
     *      operationId="toggleQuestionCategory",
     *      tags={"question_category_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to toggle question category active status",
     *      description="This method is to toggle question category active status",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"id","is_active"},
     *              @OA\Property(property="id", type="integer", example=1, description="Question category ID"),
     *              @OA\Property(property="is_active", type="boolean", example=true, description="New active state")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Question category active state updated successfully."),
     *              @OA\Property(property="data", type="object", description="Updated question category object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad request",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid input data.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You are not authorized to modify this question category.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Question category not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Question category not found.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function toggleQuestionCategory(Request $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('superadmin') && !$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('You do not have permission to toggle question categories.');
            }

            // ==================== VALIDATE INPUT ====================
            $request->validate([
                'id' => 'required|integer|exists:question_categories,id',
            ]);

            $questionCategory = QuestionCategory::findOrFail($request->id);

            // ==================== BUSINESS VALIDATION ====================
            if ($user->hasRole('superadmin')) {
                // Superadmin can ONLY toggle default categories
                if (!$questionCategory->is_default) {
                    throw new AccessDeniedHttpException('Superadmin can only toggle default question categories.');
                }
            } else {
                // Business owner can ONLY toggle their own business categories (NOT default)
                if ($questionCategory->is_default) {
                    throw new AccessDeniedHttpException('Cannot toggle default question categories.');
                }

                if ($questionCategory->business_id !== $user->business_id) {
                    throw new AccessDeniedHttpException('Question category ' . $questionCategory->id . ' belongs to another business.');
                }
            }

            // ==================== TOGGLE ACTIVE STATE ====================
            $questionCategory->is_active = !$questionCategory->is_active;
            $questionCategory->save();

            // Reload to get fresh data
            $questionCategory->refresh();
            $questionCategory->load(['parent', 'children']);

            return response()->json([
                'success' => true,
                'message' => 'Question category active state updated successfully.',
                'data' => $questionCategory
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Delete(
     *      path="/v1.0/question-categories/{ids}",
     *      operationId="deleteQuestionCategories",
     *      tags={"question_category_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to delete question categories by IDs",
     *      description="This method is to delete question categories by IDs",
     *
     *      @OA\Parameter(
     *          name="ids",
     *          in="path",
     *          description="Comma-separated list of question category IDs to delete (e.g., 1,2,3)",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              example="1,2,3"
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Question categories deleted successfully."),
     *              @OA\Property(property="deleted_count", type="integer", example=3)
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Invalid IDs provided",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid question category IDs provided.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Question categories not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Question categories not found: 4, 5")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function deleteQuestionCategories(string $ids, Request $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('superadmin') && !$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('You do not have permission to delete question categories.');
            }

            // ==================== PARSE AND VALIDATE IDS ====================
            $idArray = array_filter(array_map('intval', explode(',', $ids)));

            if (empty($idArray)) {
                throw new BadRequestHttpException('Invalid question category IDs provided: ' . $ids);
            }

            // ==================== FETCH CATEGORIES ====================
            $questionCategories = QuestionCategory::whereIn('id', $idArray)->get();

            $foundIds = $questionCategories->pluck('id')->toArray();
            $missingIds = array_diff($idArray, $foundIds);

            if (!empty($missingIds)) {
                throw new NotFoundHttpException('Question categories not found: ' . implode(', ', $missingIds));
            }

            // ==================== VALIDATE PERMISSIONS ====================
            foreach ($questionCategories as $category) {
                if ($user->hasRole('superadmin')) {
                    // Superadmin can ONLY delete default categories
                    if (!$category->is_default) {
                        throw new AccessDeniedHttpException('Superadmin can only delete default question categories. Category ID ' . $category->id . ' is not a default category.');
                    }
                } else {
                    // Business owner can ONLY delete their own business categories (NOT default)
                    if ($category->is_default) {
                        throw new AccessDeniedHttpException('Cannot delete default question categories. Category ID ' . $category->id . ' is a default category.');
                    }

                    if ($category->business_id !== $user->business_id) {
                        throw new AccessDeniedHttpException('You do not own this business. Category ID ' . $category->id . ' belongs to another business.');
                    }
                }
            }

            // ==================== DELETE CATEGORIES ====================
            $deletedCount = QuestionCategory::whereIn('id', $idArray)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Question categories deleted successfully.',
                'data' => ['deleted_count' => $deletedCount]
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Post(
     *      path="/v1.0/question-categories/with-sub-category",
     *      operationId="createQuestionCategoryWithSubCategory",
     *      tags={"question_category_management"},
     *       security={
     *           {"bearerAuth": {}}
     *      },
     *      summary="Create question category and sub-categories together",
     *      description="Create a new question category and link it with multiple sub-categories",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"title", "description", "sub_category"},
     *            @OA\Property(property="title", type="string", format="string", example="Staff Performance"),
     *            @OA\Property(property="description", type="string", format="string", example="Category for staff related questions"),
     *            @OA\Property(property="is_active", type="boolean", example=true),
     *            @OA\Property(
     *                property="sub_category",
     *                type="array",
     *                @OA\Items(
     *                    type="object",
     *                    required={"title"},
     *                    @OA\Property(property="title", type="string", example="Punctuality"),
     *                    @OA\Property(property="description", type="string", example="Questions about staff punctuality"),
     *                    @OA\Property(property="is_active", type="boolean", example=true)
     *                )
     *            )
     *         ),
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Created successfully",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   @OA\JsonContent()
     *   )
     * )
     */
    public function createQuestionCategoryWithSubCategory(Request $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can create business services.');
            }

            // ==================== VALIDATION ====================
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'is_active' => 'nullable|boolean',
                'sub_category' => 'array|present',
                'sub_category.*.title' => 'required|string|max:255',
                'sub_category.*.description' => 'nullable|string|max:255',
                'sub_category.*.is_active' => 'nullable|boolean',
            ]);

            return DB::transaction(function () use ($request, $user) {
                // ==================== BUSINESS VALIDATION ====================
                if (!$user->business_id) {
                    throw new AccessDeniedHttpException('No business associated with your account.');
                }

                // 1. Create Business Service
                $servicePayload = [
                    'business_id' => $user->business_id,
                    'title' => $request->title,
                    'description' => $request->description,
                    'is_active' => $request->input('is_active', true),
                ];

                $question = QuestionCategory::create($servicePayload);

                // 2. Handle Business Areas
                if ($request->has('sub_category')) {
                    foreach ($request->sub_category as $areaData) {
                        $areaPayload = [
                            'business_id' => $user->business_id,
                            'parent_question_category_id' => $question->id,
                            'title' => $areaData['title'],
                            'description' => $areaData['description'] ?? null,
                            'is_active' => $areaData['is_active'] ?? true,
                        ];
                        QuestionCategory::create($areaPayload);
                    }
                }

                $question->load('children');

                return response()->json([
                    "success" => true,
                    "message" => "Question category and sub categories created successfully",
                    "data" => $question
                ], 201);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }
}
