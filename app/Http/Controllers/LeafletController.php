<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\LeafletCreateRequest;
use App\Http\Requests\LeafletUpdateRequest;
use App\Models\Business;
use App\Models\Leaflet;
use Exception;
use Illuminate\Http\Request;

class LeafletController extends Controller
{
    /**
     * @OA\Post(
     *   path="/v1.0/leaflet/create",
     *   operationId="insertLeaflet",
     *   tags={"leaflet"},
     *   security={{"bearerAuth": {}}},
     *   summary="Create a leaflet",
     *   description="Store a new leaflet for a business",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"title","business_id","leaflet_data","type"},
     *       @OA\Property(property="title", type="string", example="Promo Leaflet Q4"),
     *       @OA\Property(property="business_id", type="integer", example=1),
     *       @OA\Property(property="thumbnail", type="string", example=""),
     *       @OA\Property(
     *         property="leaflet_data",
     *         type="object",
     *         @OA\Property(property="pages", type="integer", example=2),
     *         @OA\Property(property="elements", type="array", 
     *         @OA\Items(type="object")
     *         )
     *       ),
     *       @OA\Property(property="type", type="string", example="menu")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Business not found"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */

    public function insertLeaflet(LeafletCreateRequest $request)
    {
        $body = $request->validated();

        if (!$request->user()->hasRole('superadmin')) {
            $business = Business::where('id', $body['business_id'])->first();
            if (!$business) {
                return response()->json(['message' => 'business not found'], 404);
            }
        }

        $leaflet = Leaflet::create($body);
        return response($leaflet, 200);
    }

    /**
     * @OA\Put(
     *   path="/v1.0/leaflet/update/{id}",
     *   operationId="editLeaflet",
     *   tags={"leaflet"},
     *   security={{"bearerAuth": {}}},
     *   summary="Update a leaflet",
     *   description="Update an existing leaflet for a business.",
     *   @OA\Parameter(
     *     name="id", in="path", required=true,
     *     description="Leaflet id", @OA\Schema(type="integer"), example=1
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       type="object",
     *       required={"business_id"},
     *       @OA\Property(property="title", type="string", example="Updated Promo Leaflet"),
     *       @OA\Property(property="business_id", type="integer", example=1),
     *       @OA\Property(property="thumbnail", type="string", example=""),
     *       @OA\Property(
     *         property="leaflet_data",
     *         type="object",
     *         @OA\Property(property="pages", type="integer", example=2),
     *         @OA\Property(property="elements", type="array", 
     *         @OA\Items(type="object")
     *          )
     *       ),
     *       @OA\Property(property="type", type="string", example="menu")
     *     )
     *   ),
     *
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Business/Leaflet not found"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */

    public function editLeaflet($id, LeafletUpdateRequest $request)
    {
        $body = $request->validated();

        if (!$request->user()->hasRole('superadmin')) {
            $business = Business::where('id', $body['business_id'])->first();
            if (!$business) {
                return response()->json(['message' => 'business not found'], 404);
            }
        }

        $leaflet = tap(Leaflet::where(['id' => $id]))
            ->update($body)
            ->first();

        if (!$leaflet) {
            return response()->json(['message' => 'leaflet not found'], 404);
        }

        return response($leaflet, 200);
    }

    /**
     * @OA\Get(
     *   path="/v1.0/leaflet/get",
     *   operationId="getAllLeaflet",
     *   tags={"leaflet"},
     *   summary="List leaflets",
     *   description="Get leaflets filtered by business and/or type",
     *   @OA\Parameter(
     *     name="business_id", in="query", required=false,
     *     description="Filter by business id",
     *     @OA\Schema(type="integer"), example=1
     *   ),
     *   @OA\Parameter(
     *     name="type", in="query", required=false,
     *     description="Filter by leaflet type",
     *     @OA\Schema(type="string"), example="menu"
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function getAllLeaflet(Request $request)
    {
        $leafletsQuery = Leaflet::filter()
            ->orderByDesc('id');

        if ($request->has('perPage')) {
            $leaflets = $leafletsQuery->paginate($request->perPage);
        } else {
            $leaflets = $leafletsQuery->get();
        }

        return response($leaflets, 200);
    }

    /**
     * @OA\Get(
     *   path="/v1.0/leaflet/get/{id}",
     *   operationId="leafletById",
     *   tags={"leaflet"},
     *   summary="Get a leaflet by id",
     *   description="Get a single leaflet by id",
     *   @OA\Parameter(
     *     name="id", in="path", required=true,
     *     description="Leaflet id", @OA\Schema(type="integer"), example=1
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    public function leafletById($id, Request $request)
    {
        $leaflet = Leaflet::where('id', $id)->first();
        if (!$leaflet) {
            return response()->json(['message' => 'leaflet not found'], 404);
        }
        return response($leaflet, 200);
    }

    /**
     * @OA\Delete(
     *   path="/v1.0/leaflet/{ids}",
     *   operationId="leafletDeleteById",
     *   tags={"leaflet"},
     *   security={{"bearerAuth": {}}},
     *   summary="Delete leaflets by ids",
     *   description="Delete one or multiple leaflets by ids for the authenticated user's business",
     *   @OA\Parameter(
     *     name="ids", in="path", required=true,
     *     description="Leaflet ids (comma-separated for multiple)", @OA\Schema(type="string"), example="1,2,3"
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Leaflet(s) not found")
     * )
     */
    public function leafletDeleteById($ids, Request $request)
    {
        try {
            $idsArray = explode(',', $ids);
            $idsArray = array_map('intval', $idsArray);

            $businessId = $request->user()->business_id;

            if (!$request->user()->hasRole('superadmin')) {
                $business = Business::where('id', $businessId)->first();
                if (!$business) {
                    return response()->json(['message' => 'business not found'], 404);
                }
            }

            $existingIds = Leaflet::whereIn('id', $idsArray)
                ->where('business_id', $businessId)
                ->pluck('id')
                ->toArray();

            $nonExistingIds = array_diff($idsArray, $existingIds);

            if (!empty($nonExistingIds)) {
                return response()->json([
                    'message' => 'Some leaflets were not found or do not belong to your business',
                    'non_existing_ids' => array_values($nonExistingIds)
                ], 404);
            }

            Leaflet::whereIn('id', $idsArray)
                ->where('business_id', $businessId)
                ->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Leaflets deleted successfully',
                'deleted_count' => count($existingIds)
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *   path="/v1.0/leaflet-image",
     *   operationId="insertLeafletImage",
     *   tags={"leaflet"},
     *   security={{"bearerAuth": {}}},
     *   summary="Upload a leaflet image",
     *   description="Upload and store a leaflet image (returns the stored path)",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         type="object",
     *         required={"image"},
     *         @OA\Property(
     *           property="image",
     *           description="Image to upload",
     *           type="string",
     *           format="binary"
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function insertLeafletImage(ImageUploadRequest $request)
    {
        try {
            $data = $request->validated();
            $location = "leaflet_image";
            $newFileName = time() . '_' . $data['image']->getClientOriginalName();
            $data['image']->move(public_path($location), $newFileName);

            return response()->json([
                'image' => "/" . $location . "/" . $newFileName,
            ], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
