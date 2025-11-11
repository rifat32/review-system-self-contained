<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageUploadRequest;
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

    public function insertLeaflet(Request $request)
    {
        $body = $request->toArray();

        if (!$request->user()->hasRole('superadmin')) {
            $business = Business::where('id', $body['business_id'] ?? null)->first();
            if (!$business) {
                return response()->json(['message' => 'business not found'], 404);
            }
        }

        $leaflet = Leaflet::create($body);
        return response($leaflet, 200);
    }

    /**
     * @OA\Put(
     *   path="/v1.0/leaflet",
     *   operationId="editLeaflet",
     *   tags={"leaflet"},
     *   security={{"bearerAuth": {}}},
     *   summary="Update a leaflet",
     *   description="Update an existing leaflet for a business.",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       type="object",
     *       required={"business_id"},
     *       @OA\Property(property="id", type="integer", example=1),
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

    public function editLeaflet(Request $request)
    {
        $body = $request->toArray();

        if (!$request->user()->hasRole('superadmin')) {
            $business = Business::where('id', $body['business_id'] ?? null)->first();
            if (!$business) {
                return response()->json(['message' => 'business not found'], 404);
            }
        }

        $leaflet = tap(Leaflet::where(['id' => $body['id'] ?? null]))
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
        $leafletsQuery = Leaflet::query();

        if (!empty($request->business_id)) {
            $leafletsQuery->where('business_id', $request->business_id);
        }
        if (!empty($request->type)) {
            $leafletsQuery->where('type', $request->type);
        }

        $leaflets = $leafletsQuery->orderByDesc('id')->get();
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
     *   path="/v1.0/leaflet/{business_id}/{id}",
     *   operationId="leafletDeleteById",
     *   tags={"leaflet"},
     *   security={{"bearerAuth": {}}},
     *   summary="Delete a leaflet by id",
     *   description="Delete a leaflet by id for a business",
     *   @OA\Parameter(
     *     name="business_id", in="path", required=true,
     *     description="Business id", @OA\Schema(type="integer"), example=1
     *   ),
     *   @OA\Parameter(
     *     name="id", in="path", required=true,
     *     description="Leaflet id", @OA\Schema(type="integer"), example=5
     *   ),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Business/Leaflet not found")
     * )
     */
    public function leafletDeleteById($business_id, $id, Request $request)
    {
        if (!$request->user()->hasRole('superadmin')) {
            $business = Business::where('id', $business_id)->first();
            if (!$business) {
                return response()->json(['message' => 'business not found'], 404);
            }
        }

        $deleted = Leaflet::where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['message' => 'leaflet not found'], 404);
        }

        return response(['ok' => true], 200);
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
