<?php

namespace App\Http\Controllers;

use App\Models\ServicePlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServicePlanController extends Controller
{
    /**
     * @OA\Get(
     *      path="/service-plans",
     *      operationId="getServicePlans",
     *      tags={"service-plans"},
     *      summary="List all active service plans",
     *      description="Returns a list of all active service plans with their modules.",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function index()

    {
        $plans = ServicePlan::with('modules')->where('is_active', true)->get();
        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    /**
     * @OA\Post(
     *      path="/service-plans",
     *      operationId="storeServicePlan",
     *      tags={"service-plans"},
     *      summary="Create a new service plan",
     *      description="Creates a new service plan and optionally syncs modules.",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name", "price", "duration_months", "openai_token_limit"},
     *              @OA\Property(property="name", type="string", example="Pro Plan"),
     *              @OA\Property(property="description", type="string", example="Full featured plan"),
     *              @OA\Property(property="price", type="number", format="float", example=29.99),
     *              @OA\Property(property="duration_months", type="integer", example=1),
     *              @OA\Property(property="openai_token_limit", type="integer", example=10000),
     *              @OA\Property(property="module_ids", type="array", @OA\Items(type="integer", example=1))
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Service plan created successfully",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'duration_months' => 'required|integer',
            'openai_token_limit' => 'required|integer',
            'free_trial_duration_date' => 'required|integer',
            'module_ids' => 'nullable|array',
            'module_ids.*' => 'exists:modules,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $plan = ServicePlan::create($request->only([
            'name',
            'description',
            'price',
            'duration_months',
            'openai_token_limit',
            'free_trial_duration_date'
        ]) + ['created_by' => auth()->id()]);

        if ($request->has('module_ids')) {
            $plan->modules()->sync($request->module_ids);
        }

        return response()->json([
            'success' => true,
            'message' => 'Service plan created successfully',
            'data' => $plan->load('modules')
        ]);
    }

    /**
     * @OA\Get(
     *      path="/service-plans/{id}",
     *      operationId="getServicePlanById",
     *      tags={"service-plans"},
     *      summary="Get details of a specific service plan",
     *      description="Returns plan details with modules by ID.",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="The ID of the service plan",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Service plan not found",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function show($id)
    {
        $plan = ServicePlan::with('modules')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $plan
        ]);
    }

    /**
     * @OA\Put(
     *      path="/service-plans/{id}",
     *      operationId="updateServicePlan",
     *      tags={"service-plans"},
     *      summary="Update an existing service plan",
     *      description="Updates plan details and optionally syncs modules.",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="The ID of the service plan",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="name", type="string", example="Updated Pro Plan"),
     *              @OA\Property(property="price", type="number", format="float", example=39.99),
     *              @OA\Property(property="is_active", type="boolean", example=true),
     *              @OA\Property(property="module_ids", type="array", @OA\Items(type="integer", example=1))
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Service plan updated successfully",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Service plan not found",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function update(Request $request, $id)
    {
        $plan = ServicePlan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric',
            'duration_months' => 'sometimes|required|integer',
            'openai_token_limit' => 'sometimes|required|integer',
            'free_trial_duration_date' => 'required|integer',
            'is_active' => 'sometimes|required|boolean',
            'module_ids' => 'nullable|array',
            'module_ids.*' => 'exists:modules,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $plan->update($request->only([
            'name',
            'description',
            'price',
            'duration_months',
            'openai_token_limit',
            'free_trial_duration_date',
            'is_active'
        ]));

        if ($request->has('module_ids')) {
            $plan->modules()->sync($request->module_ids);
        }

        return response()->json([
            'success' => true,
            'message' => 'Service plan updated successfully',
            'data' => $plan->load('modules')
        ]);
    }

    /**
     * @OA\Delete(
     *      path="/service-plans/{id}",
     *      operationId="deleteServicePlan",
     *      tags={"service-plans"},
     *      summary="Deactivate a service plan",
     *      description="Marks a service plan as inactive.",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="The ID of the service plan",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Service plan deactivated successfully",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Service plan not found",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function destroy($id)
    {
        $plan = ServicePlan::findOrFail($id);
        $plan->update(['is_active' => false]);
        return response()->json([
            'success' => true,
            'message' => 'Service plan deactivated successfully'
        ]);
    }
}
