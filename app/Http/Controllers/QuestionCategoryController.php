<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuestionCategoryRequest;
use App\Http\Utils\ErrorUtil;
use App\Models\Business;
use App\Models\QuestionCategory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            return DB::transaction(function () use ($request) {
                $payload_data = $request->validated();

                // Set created_by if user is authenticated
                // ADD CREATOR
                $authUser = $request->user();
                $business_id = $request->user()->business()->value('id');
                $payload_data['created_by'] = $authUser->id;
                $payload_data['business_id'] = $business_id ?? null;
                // Create the question category
                $questionCategory = QuestionCategory::create($payload_data);

                // Return success response
                return response()->json([
                    "success" => true,
                    "message" => "Question category created successfully",
                    "data" => $questionCategory
                ], 201);
            });
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
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
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction (asc, desc)",
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
            $query = QuestionCategory::with(['parent', 'children'])->filters();

            $questionCategories = retrieve_data($query);

            return response()->json([
                "success" => true,
                "message" => "Question categories retrieved successfully",
                "data" => $questionCategories
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
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
                ->find($id);

            if (!$questionCategory) {
                return response()->json([
                    "success" => false,
                    "message" => "Question category not found"
                ], 404);
            }

            return response()->json([
                "success" => true,
                "message" => "Question category retrieved successfully",
                "data" => $questionCategory
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
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
     *            required={, "title"},
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
            return DB::transaction(function () use ($request, $id) {
                $questionCategory = QuestionCategory::find($id);

                if (!$questionCategory) {
                    return response()->json([
                        "success" => false,
                        "message" => "Question category not found"
                    ], 404);
                }

                $payload_data = $request->validated();

                if ($questionCategory->is_default) {
                    // Prevent changing is_default of default category
                    return response()->json([
                        "success" => false,
                        "message" => "Default question category cannot be modified"
                    ], 406);
                }

                // Update the question category
                $questionCategory->update($payload_data);

                // Load relationships for response
                $questionCategory->load(['parent', 'children']);

                return response()->json([
                    "success" => true,
                    "message" => "Question category updated successfully",
                    "data" => $questionCategory
                ], 200);
            });
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
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
            // Validate input
            $request->validate([
                'id' => 'required|integer|exists:question_categories,id',
            ]);

            $questionCategory = QuestionCategory::findOrFail($request->id);
            // $user = $request->user();

            // // Check authorization
            // if ($user->hasRole('superadmin')) {
            //     // Superadmin can toggle any category
            // } else {
            //     // Regular users can only toggle categories for their businesses or default categories
            //     $userBusinessIds = $user->businesses()->pluck('id')->toArray();

            //     if (!$questionCategory->is_default && !in_array($questionCategory->business_id, $userBusinessIds)) {
            //         return response()->json([
            //             'success' => false,
            //             'message' => 'You are not authorized to modify this question category.'
            //         ], 403);
            //     }
            // }
            if ($questionCategory->is_default) {
                // Prevent changing is_default of default category
                return response()->json([
                    "success" => false,
                    "message" => "Default question category cannot be modified"
                ], 406);
            }

            // Update the is_active field
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
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
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
            // Parse IDs (comma-separated)
            $idArray = array_filter(array_map('intval', explode(',', $ids)));

            if (empty($idArray)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid question category IDs provided.'
                ], 400);
            }

            // Get all question categories to verify they exist
            $questionCategories = QuestionCategory::whereIn('id', $idArray)->get();

            // Check if all requested IDs exist
            $foundIds = $questionCategories->pluck('id')->toArray();
            $missingIds = array_diff($idArray, $foundIds);

            if (!empty($missingIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Question categories not found: ' . implode(', ', $missingIds)
                ], 404);
            }

            // Delete the question categories
            $deletedCount = QuestionCategory::whereIn('id', $idArray)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Question categories deleted successfully.',
                'data' => ['deleted_count' => $deletedCount]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
        }
    }
}
