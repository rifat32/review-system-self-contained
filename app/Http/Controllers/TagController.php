<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagMultipleRequest;
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
}
