<?php

namespace App\Http\Utils;

use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\Module;
use App\Models\ServicePlan;
use App\Models\ServicePlanModule;
use Exception;

trait ModuleUtil
{
    // this function do all the task and returns transaction id or -1
    public function isModuleEnabled($module_name, $throwErr = true)
    {
        $user = auth()->user();
        if (empty($user->business_id)) {
            return true;
        }

        $query_params = [
            'name' => $module_name,
        ];
        $module = Module::where($query_params)->first();

        if (empty($module)) {
            if ($throwErr) {
                throw new Exception('No Module Found', 401);
            } else {
                return false;
            }
        }


        if (empty($module->is_enabled)) {
            if ($throwErr) {
                throw new Exception('Module is not enabled', 401);
            } else {
                return false;
            }
        }


        $business = Business::find($user->business_id);
        if (empty($business)) {
            if ($throwErr) {
                throw new Exception('No Business Found', 401);
            } else {
                return false;
            }
        }


        // $business_tier_id = $business->service_plan ? $business->service_plan->business_tier->id : 1;


        $is_enabled = false;


        // $businessTierModule =    BusinessTierModule::where([
        //     "business_tier_id" => $business_tier_id,
        //     "module_id" => $module->id
        // ])
        //     ->first();

        // if (!empty($businessTierModule)) {
        //     $is_enabled = $businessTierModule->is_enabled;
        // }



        $servicePlanModule =  ServicePlanModule::where([
            "service_plan_id" => $business->service_plan ? $business->service_plan->id : 0,
            "module_id" => $module->id
        ])
            ->first();


        if (!empty($servicePlanModule)) {
            $is_enabled = $servicePlanModule->is_enabled;
        }


        $businessModule =    BusinessModule::where([
            "business_id" => $business->id,
            "module_id" => $module->id
        ])
            ->first();


        if (!empty($businessModule)) {
            $is_enabled = $businessModule->is_enabled;
        }



        if (!$is_enabled && $throwErr) {
            throw new Exception(($module->name . 'Module is not enabled'), 403);
        }

        return $is_enabled;
    }



    public function getModulesFunc($business)
    {
        $service_plan_modules =   ServicePlanModule::where([
            "service_plan_id" => $business->service_plan_id,
        ])
            ->get();


        $modules = Module::where('modules.is_enabled', 1)
            ->orderBy("modules.name", "ASC")

            ->select("id", "name")
            ->get()

            ->map(function ($item) use ($business, $service_plan_modules) {
                $item->is_enabled = 0;

                $service_plan_module = $service_plan_modules->first(function ($plan) use ($item) {
                    return $plan->module_id == $item->id;
                });

                if (!empty($service_plan_module)) {
                    $item->is_enabled = $service_plan_module->is_enabled;
                }



                $businessModule =    BusinessModule::where([
                    "business_id" => $business->id,
                    "module_id" => $item->id
                ])
                    ->first();

                if (!empty($businessModule)) {
                    $item->is_enabled = $businessModule->is_enabled;
                }

                $item->businessModule = $businessModule;

                $item->service_plan_module = $service_plan_module;

                return $item;
            });

        return $modules;
    }
}
