<?php

namespace App\Http\Utils;

use App\Models\ServicePlanDiscountCode;
use Exception;

trait DiscountUtil
{
    /**
     * Get discount amount based on service plan and discount code.
     */
    public function getDiscountAmount(array $request_data)
    {
        if (!empty($request_data["service_plan_id"]) && !empty($request_data["service_plan_discount_code"])) {
            $discount = ServicePlanDiscountCode::where([
                "code" => $request_data["service_plan_discount_code"],
                "service_plan_id" => $request_data["service_plan_id"],
            ])->first();

            if (!$discount) {
                throw new Exception("Invalid discount code", 403);
            }

            return $discount->discount_amount;
        }

        return 0;
    }
}
