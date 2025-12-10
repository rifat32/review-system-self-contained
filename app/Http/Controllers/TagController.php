<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagMultipleRequest;
use App\Models\ReviewValueNew;
use App\Models\StarTag;
use App\Models\Tag;
use App\Rules\ValidBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *   schema="Tag",
 *   type="object",
 *   required={"id","tag"},
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="tag", type="string", example="How was this?"),
 *   @OA\Property(property="business_id", type="integer", nullable=true, example=1),
 *   @OA\Property(property="is_default", type="boolean", example=false),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-06T10:12:00Z"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-06T10:12:00Z")
 * )
 *
 * @OA\Schema(
 *   schema="ApiSuccessTag",
 *   type="object",
 *   @OA\Property(property="success", type="boolean", example=true),
 *   @OA\Property(property="message", type="string", example="OK"),
 *   @OA\Property(property="data", ref="#/components/schemas/Tag")
 * )
 *
 * @OA\Schema(
 *   schema="ApiSuccessTagList",
 *   type="object",
 *   @OA\Property(property="success", type="boolean", example=true),
 *   @OA\Property(property="message", type="string", example="OK"),
 *   @OA\Property(
 *     property="data",
 *     type="array",
 *     @OA\Items(ref="#/components/schemas/Tag")
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="ApiError",
 *   type="object",
 *   @OA\Property(property="success", type="boolean", example=false),
 *   @OA\Property(property="message", type="string", example="Validation error"),
 *   @OA\Property(property="errors", type="object", example={"tag":{"The tag field is required."}})
 * )
 */


class TagController extends Controller
{
    /**
     * @OA\Post(
     *   path="/v1.0/tags",
     *   operationId="createTag",
     *   tags={"Tags"},
     *   security={{"bearerAuth":{}}},
     *   summary="Create a tag",
     *   description="Creates a new tag. business_id can be null. If user is superadmin and business_id is empty, server may set is_default=true.",
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"tag"},
     *       @OA\Property(property="tag", type="string", maxLength=255, example="How was this?"),
     *       @OA\Property(property="business_id", type="integer", nullable=true, example=1)
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Tag created successfully",
     *     @OA\JsonContent(ref="#/components/schemas/ApiSuccessTag")
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Unprocessable Content / Validation error",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   )
     * )
     */
    public function createTag(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tag' => 'required|string|max:255',
            'business_id' => ['nullable', 'integer', new ValidBusiness()],
        ]);

        if ($request->user()->hasRole('superadmin') && empty($data['business_id'])) {
            $data['is_default'] = true;
        }

        $tag = Tag::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tag created successfully',
            'data' => $tag,
        ], 200);
    }

    /**
     * @OA\Get(
     *   path="/v1.0/tags",
     *   operationId="getAllTags",
     *   tags={"Tags"},
     *   security={{"bearerAuth":{}}},
     *   summary="Get all tags",
     *   description="Returns tags based on user role and optional filters. Superadmin gets only default tags (business_id=null, is_default=1). Non-superadmin gets business tags (business_id=business_id, is_default=0) plus default tags (business_id=null, is_default=1).",
     *
     *   @OA\Parameter(
     *     name="business_id",
     *     in="query",
     *     required=false,
     *     description="Business ID. Required for non-superadmin users. Ignored for superadmin (superadmin always receives default tags).",
     *     @OA\Schema(type="integer", nullable=true, example=1)
     *   ),
     *
     *   @OA\Parameter(
     *     name="is_active",
     *     in="query",
     *     required=false,
     *     description="Filter by active status (0/1 or true/false).",
     *     @OA\Schema(type="string", enum={"0","1","true","false"}, example="1")
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/ApiSuccessTagList")
     *   ),
     *
     *   @OA\Response(
     *     response=422,
     *     description="Validation error (e.g., business_id missing for non-superadmin)",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   ),
     *
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Business not found (when business_id provided but invalid)",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   )
     * )
     */

    public function getAllTags(Request $request): JsonResponse
    {
        // VALIDATE QUERY
        $request->validate([
            'business_id' => ['nullable', 'integer', new ValidBusiness()],
            'is_active'   => ['nullable', 'in:0,1,true,false'],
        ]);

        $tags = Tag::query()
            ->when($request->filled('business_id'), fn($q) => $q->where('business_id', $request->integer('business_id')))
             ->orWhere(["business_id" => NULL, "is_default" => 1])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Tags fetched successfully',
            'data' => $tags,
        ], 200);
    }

    /**
     * @OA\Get(
     *   path="/v1.0/tags/{id}",
     *   operationId="getTagById",
     *   tags={"Tags"},
     *   security={{"bearerAuth":{}}},
     *   summary="Get a tag by id",
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Tag id",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(ref="#/components/schemas/ApiSuccessTag")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not found",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   )
     * )
     */

    public function getTagById(int $id): JsonResponse
    {
        $tag = Tag::find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Tag not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tag fetched successfully',
            'data' => $tag,
        ], 200);
    }

    /**
     * @OA\Patch(
     *   path="/v1.0/tags/{id}",
     *   operationId="updateTag",
     *   tags={"Tags"},
     *   security={{"bearerAuth":{}}},
     *   summary="Update a tag",
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Tag id",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="tag", type="string", maxLength=255, example="Updated tag"),
     *       @OA\Property(property="is_active", type="integer", nullable=true, example=1)
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Updated",
     *     @OA\JsonContent(ref="#/components/schemas/ApiSuccessTag")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Not found",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(ref="#/components/schemas/ApiError")
     *   )
     * )
     */

    public function updateTag(Request $request, int $id): JsonResponse
    {
        $tag = Tag::find($id);

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Tag not found',
            ], 404);
        }

        // ✅ Only superadmin can update default tags
        if ((bool) $tag->is_default && !$request->user()->hasRole('superadmin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update default tags.',
            ], 403);
        }

        $data = $request->validate([
            'tag' => 'sometimes|required|string|max:255',
            "is_active" => "required|boolean"
        ]);



        $tag->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Tag updated successfully',
            'data' => $tag->fresh(),
        ], 200);
    }


    /**
     * @OA\Delete(
     *   path="/v1.0/tags/{ids}",
     *   operationId="deleteTags",
     *   tags={"Tags"},
     *   security={{"bearerAuth":{}}},
     *   summary="Delete multiple tags",
     *   description="Delete multiple tags by comma-separated ids. Example: /v1.0/tags/1,2,3 . If any id does not exist, nothing is deleted and 404 is returned with missing_ids.",
     *
     *   @OA\Parameter(
     *     name="ids",
     *     in="path",
     *     required=true,
     *     description="Comma-separated tag ids (e.g. 1,2,3)",
     *     @OA\Schema(type="string", example="1,2,3")
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Deleted",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Tags deleted successfully"),
     *       @OA\Property(property="data", nullable=true, example=null)
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Some ids not found (no deletion performed)",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Some tag ids were not found."),
     *       @OA\Property(
     *         property="missing_ids",
     *         type="array",
     *         @OA\Items(type="integer", example=99)
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=422,
     *     description="Invalid ids format",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Invalid ids format. Use comma-separated integers like: 1,2,3")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Unauthenticated.")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Forbidden.")
     *     )
     *   )
     * )
     */


    public function deleteTag(string $ids): JsonResponse
    {
        // Parse: "1,2,3" -> [1,2,3]
        $idsArray = collect(explode(',', $ids))
            ->map(fn($v) => trim($v))
            ->filter(fn($v) => $v !== '')
            ->map(fn($v) => ctype_digit($v) ? (int) $v : null);

        // Validate format
        if ($idsArray->contains(null) || $idsArray->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid ids format. Use comma-separated integers like: 1,2,3',
            ], 422);
        }

        $idsArray = $idsArray->unique()->values();
        $idsList = $idsArray->all();

        // Check existence
        $existingIds = Tag::whereIn('id', $idsList)->pluck('id')->all();
        $missingIds = array_values(array_diff($idsList, $existingIds));

        if (!empty($missingIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some tag ids were not found.',
                'missing_ids' => $missingIds,
            ], 404);
        }

        // Delete all
        Tag::whereIn('id', $idsList)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tags deleted successfully',
            'data' => null,
        ], 200);
    }

    /**
     * @OA\Post(
     *   path="/v1.0/tags/multiple/{businessId}",
     *   operationId="createMultipleTags",
     *   tags={"Tags"},
     *   security={{"bearerAuth":{}}},
     *   summary="Create multiple tags for a business",
     *   description="Creates multiple tags at once. For superadmin: creates default tags (business_id=null, is_default=1). For non-superadmin: creates business tags (business_id={businessId}, is_default=0). If any tag already exists (business tag or default tag), the API returns 409 with duplicate_indexes and does not create anything.",
     *
     *   @OA\Parameter(
     *     name="businessId",
     *     in="path",
     *     required=true,
     *     description="Business ID",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"tags"},
     *       @OA\Property(
     *         property="tags",
     *         type="array",
     *         minItems=1,
     *         description="Array of tag strings (duplicates will be removed before checking/creating).",
     *         @OA\Items(type="string", maxLength=255, example="Service")
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=201,
     *     description="Tags created",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Tags created successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/Tag")
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=409,
     *     description="Duplicate tags found (no records created)",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Duplicate tags found"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(
     *           property="duplicate_indexes",
     *           type="array",
     *           description="Indexes of duplicates based on the unique tag list (after duplicates removed).",
     *           @OA\Items(type="integer", example=0)
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="The given data was invalid."),
     *       @OA\Property(property="errors", type="object", example={"tags":{"The tags field is required."}})
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Unauthenticated.")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden (not business owner)",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Forbidden.")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Business not found",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Not Found.")
     *     )
     *   )
     * )
     */


    public function createMultipleTags(int $businessId, StoreTagMultipleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $isSuperAdmin = $request->user()->hasRole('superadmin');

        // Normalize + unique (keep same "index" meaning as your old code: indexing after unique())
        $uniqueTags = collect($validated['tags'])
            ->map(fn($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->values();

        if ($uniqueTags->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid tags provided',
                'data' => ['duplicate_indexes' => []],
            ], 422);
        }

        // Find existing tags in ONE query
        $existingTags = Tag::query()
            ->where('tag', '!=', '')
            ->whereIn('tag', $uniqueTags->all())
            ->where(function ($q) use ($isSuperAdmin, $businessId) {
                if ($isSuperAdmin) {
                    $q->whereNull('business_id')->where('is_default', 1);
                } else {
                    $q->where(function ($q2) use ($businessId) {
                        $q2->where('business_id', $businessId)->where('is_default', 0);
                    })->orWhere(function ($q2) {
                        $q2->whereNull('business_id')->where('is_default', 1);
                    });
                }
            })
            ->pluck('tag')
            ->all();

        $existingSet = array_flip($existingTags);

        // Duplicate indexes based on uniqueTags array position (same as your old foreach)
        $duplicateIndexes = $uniqueTags
            ->map(fn($tag, $idx) => isset($existingSet[$tag]) ? $idx : null)
            ->filter(fn($v) => $v !== null)
            ->values()
            ->all();

        if (!empty($duplicateIndexes)) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate tags found',
                'data' => [
                    'duplicate_indexes' => $duplicateIndexes,
                ],
            ], 409);
        }

        // Bulk insert (faster than create() in loop)
        $now = now();
        $rows = $uniqueTags->map(fn($tag) => [
            'tag' => $tag,
            'is_default' => $isSuperAdmin ? 1 : 0,
            'business_id' => $isSuperAdmin ? null : $businessId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        Tag::insert($rows);

        // Fetch inserted rows to return
        $createdTags = Tag::query()
            ->whereIn('tag', $uniqueTags->all())
            ->where('is_default', $isSuperAdmin ? 1 : 0)
            ->when(!$isSuperAdmin, fn($q) => $q->where('business_id', $businessId))
            ->when($isSuperAdmin, fn($q) => $q->whereNull('business_id'))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Tags created successfully',
            'data' => $createdTags,
        ], 201);
    }





















 // ##################################################
    // This method is to store tag
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/review-new/create/tags",
     *      operationId="storeTag",
     *      tags={"z.unused"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store tag",
     *      description="This method is to store tag",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"tag","business_id"},
     *            @OA\Property(property="tag", type="string", format="string",example="How was this?"),
     *  @OA\Property(property="business_id", type="number", format="number",example="1"),

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
    public function storeTag(Request $request)
    {
        $question = [
            'tag' => $request->tag,
            'business_id' => $request->business_id,
            'is_active' => $request->is_active,
        ];
        if ($request->user()->hasRole("superadmin")) {
            $question["is_default"] = true;
        } else {
            $business =    Business::where(["id" => $request->business_id])->first();
            if (!$business) {
                return response()->json(["message" => "No Business Found"]);
            }
        }



        $createdQuestion =    Tag::create($question);


        return response($createdQuestion, 201);




        return response($createdQuestion, 201);
    }
    /**
     *
     * @OA\Post(
     *      path="/v1.0/review-new/create/tags/multiple/{businessId}",
     *      operationId="storeTagMultiple",
     *      tags={"z.unused"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Store multiple tags for a business",
     *      description="Create multiple tags at once for a specific business. Checks for duplicate tags and validates business ownership.",
     *
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          example="1"
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"tags"},
     *              @OA\Property(
     *                  property="tags",
     *                  type="array",
     *                  @OA\Items(type="string"),
     *                  example={"Excellent Service", "Great Food", "Clean Environment"}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="Tags created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Tags created successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="tag", type="string", example="Excellent Service"),
     *                      @OA\Property(property="business_id", type="integer", example=1),
     *                      @OA\Property(property="is_default", type="boolean", example=false),
     *                      @OA\Property(property="is_active", type="boolean", example=true)
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
     *          description="Forbidden - Not business owner",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="You do not own this business")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=409,
     *          description="Duplicate tags found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Duplicate tags found"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(
     *                      property="duplicate_indexes",
     *                      type="array",
     *                      @OA\Items(type="integer"),
     *                      example={0, 2}
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */
    public function storeTagMultiple($businessId, StoreTagMultipleRequest $request)
    {
        // VALIDATE REQUEST (business ownership already checked in request)
        $validated = $request->validated();

        // GET UNIQUE TAGS
        $uniqueTags = collect($validated['tags'])->unique()->values()->all();

        // CHECK FOR DUPLICATES
        $duplicateIndexes = [];
        $isSuperAdmin = $request->user()->hasRole("superadmin");

        foreach ($uniqueTags as $index => $tagName) {
            if ($isSuperAdmin) {
                // Check if default tag already exists
                $existingTag = Tag::where([
                    "business_id" => NULL,
                    "tag" => $tagName,
                    "is_default" => 1
                ])->first();

                if ($existingTag) {
                    $duplicateIndexes[] = $index;
                }
            } else {
                // Check if business-specific tag exists
                $existingTag = Tag::where([
                    "business_id" => $businessId,
                    "is_default" => 0,
                    "tag" => $tagName
                ])->first();

                // Also check if default tag exists
                if (!$existingTag) {
                    $existingTag = Tag::where([
                        "business_id" => NULL,
                        "is_default" => 1,
                        "tag" => $tagName
                    ])->first();
                }

                if ($existingTag) {
                    $duplicateIndexes[] = $index;
                }
            }
        }

        // RETURN ERROR IF DUPLICATES FOUND
        if (count($duplicateIndexes) > 0) {
            return response()->json([
                "success" => false,
                "message" => "Duplicate tags found",
                "data" => [
                    "duplicate_indexes" => $duplicateIndexes
                ]
            ], 409);
        }

        // CREATE TAGS
        $createdTags = [];

        foreach ($uniqueTags as $tagName) {
            $tagData = [
                'tag' => $tagName,
                'is_default' => $isSuperAdmin,
                'business_id' => $isSuperAdmin ? NULL : $businessId
            ];

            $createdTag = Tag::create($tagData);
            $createdTags[] = $createdTag;
        }

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Tags created successfully",
            "data" => $createdTags
        ], 201);
    }

    // ##################################################
    // This method is to update tag
    // ##################################################
    /**
     *
     * @OA\Put(
     *      path="/review-new/update/tags",
     *      operationId="updatedTag",
     *      tags={"z.unused"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update tag",
     *      description="This method is to update tag",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"tag","id"},
     *            @OA\Property(property="tag", type="string", format="string",example="How was this?"),
     *  @OA\Property(property="id", type="number", format="number",example="1"),

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
    public function updatedTag(Request $request)
    {

        $question = [
            'tag' => $request->tag,
            'is_active' => $request->is_active
        ];
        $checkQuestion =    Tag::where(["id" => $request->id])->first();
        if ($checkQuestion->is_default == true && !$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "you can not update the question. you are not a super admin"]);
        }
        $updatedQuestion =    tap(Tag::where(["id" => $request->id]))->update(
            $question
        )
            // ->with("somthing")

            ->first();


        return response($updatedQuestion, 200);
    }
    // ##################################################
    // This method is to get tag
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/review-new/get/tags",
     *      operationId="getTag",
     *      tags={"review.setting.tag"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get tag",
     *      description="This method is to get tag",
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *      *         @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="is_active",
     *         required=false,
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
    public function   getTag(Request $request)
    {

        $is_dafault = false;
        $businessId = $request->business_id;

        if ($request->user()->hasRole("superadmin")) {
            $is_dafault = true;
            $businessId = NULL;
            $query =  Tag::where(["business_id" => NULL, "is_default" => true])
                ->when(request()->filled("is_active"), function ($query) {
                    $query->where("tags.is_active", request()->input("is_active"));
                });
        } else {
            $business =    Business::where(["id" => $request->business_id])->first();
            if (!$business && !$request->user()->hasRole("superadmin")) {
                return response("No Business Found", 404);
            }

            $query =  Tag::where(["business_id" => $businessId, "is_default" => 0])
                ->orWhere(["business_id" => NULL, "is_default" => 1])
                ->when(request()->filled("is_active"), function ($query) {
                    $query->where("tags.is_active", request()->input("is_active"));
                });;
        }



        $questions =  $query->get();


        return response($questions, 200);
    }
    // ##################################################
    // This method is to get tag  by id.
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/review-new/get/tags/{id}",
     *      operationId="TagById",
     *      tags={"z.unused"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get tag  by id",
     *      description="This method is to get tag  by id",
     *         @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="tag Id",
     *         required=false,
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
    public function   TagById($id, Request $request)
    {
        $questions =    Tag::where(["id" => $id])
            ->first();
        if (!$questions) {
            return response([
                "message" => "No Tag Found"
            ], 404);
        }
        return response($questions, 200);
    }
    /**
     *
     * @OA\Get(
     *      path="/review-new/get/tags/{id}/{restaurantId}",
     *      operationId="getTagById2",
     *      tags={"z.unused"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get tag  by id",
     *      description="This method is to get tag  by id",
     *         @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="tag Id",
     *         required=false,
     *      ),
     *        @OA\Parameter(
     *         name="restaurantId",
     *         in="path",
     *         description="restaurantId",
     *         required=false,
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
     *  @OA\Response(
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
    public function   getTagById2($id, $restaurantId, Request $request)
    {
        $questions =    Tag::where(["id" => $id, "business_id" => $restaurantId])
            ->first();
        if (!$questions) {
            return response([
                "message" => "No Tag Found"
            ], 404);
        }
        return response([
            "success" => true,
            "message" => "Tag Found",
            "data" => $questions
        ], 200);
    }

    // ##################################################
    // This method is to delete tag by id
    // ##################################################

    /**
     *
     * @OA\Delete(
     *      path="/review-new/delete/tags/{id}",
     *      operationId="deleteTagById",
     *      tags={"z.unused"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to delete tag  by id",
     *      description="This method is to delete tag  by id",
     *         @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="tag Id",
     *         required=false,
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
    public function   deleteTagById($id, Request $request)
    {
        $tag =    Tag::where(["id" => $id])
            ->first();
        $tagId = $tag->id;

        if ($request->user()->hasRole("superadmin") &&  $tag->is_default == 1) {
            StarTag::where(["tag_id" => $tagId])->delete();
            $tag->delete();
            ReviewValueNew::where([
                'tag_id' => $tagId
            ])
                ->delete();
        } else  if (!$request->user()->hasRole("superadmin") &&  $tag->is_default == 0) {
            StarTag::where(["tag_id" => $tagId])->delete();
            $tag->delete();
            ReviewValueNew::where([
                'tag_id' => $tagId
            ])
                ->delete();
        }



        return response(["message" => "ok"], 200);
    }







}
