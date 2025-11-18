<?php
namespace App\Http\Utils;


use App\Models\Coupon;
use Exception;
use Illuminate\Support\Facades\DB;

trait DiscountUtil
{


    // this function do all the task and returns transaction id or -1
    public function getCouponDiscount($garage_id, $code, $amount)
    {

        $coupon =  Coupon::where([
            "business_id" => $garage_id,
            "code" => $code,
            "is_active" => 1,

        ])
            // ->where('coupon_start_date', '<=', Carbon::now()->subDay())
            // ->where('coupon_end_date', '>=', Carbon::now()->subDay())
            ->first();

        if (!$coupon) {
            $error = [
                "message" => "The given data was invalid.",
                "errors" => ["coupon_code" => "no coupon is found"]
            ];
            throw new Exception(json_encode($error), 422);
        }

        if (!empty($coupon->min_total) && ($coupon->min_total > $amount)) {
            $error = [
                "message" => "The given data was invalid.",
                "errors" => ["coupon_code" => "minimim limit is " . $coupon->min_total]
            ];
            throw new Exception(json_encode($error), 422);
        }
        if (!empty($coupon->max_total) && ($coupon->max_total < $amount)) {
            $error = [
                "message" => "The given data was invalid.",
                "errors" => "maximum limit is " . $coupon->max_total
            ];
            throw new Exception(json_encode($error), 422);
        }

        if (!empty($coupon->redemptions) && $coupon->redemptions == $coupon->customer_redemptions) {
            $error = [
                "message" => "The given data was invalid.",
                "errors" => "maximum people reached"
            ];
            throw new Exception(json_encode($error), 422);
        }
        return $coupon;
    }


    public function canculate_discount($total_price, $discount_type, $discount_amount)
    {
        if (!empty($discount_type) && !empty($discount_amount)) {
            if ($discount_type == "fixed") {
                return $discount_amount;
            } else if ($discount_type == "percentage") {
                return ($total_price / 100) * $discount_amount;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    public function canculate_discount_amount($total_price, $discount_type, $discount_amount)
    {
        if (!empty($discount_type) && !empty($discount_amount)) {
            if ($discount_type == "fixed") {
                return $discount_amount;
            } else if ($discount_type == "percentage") {
                return ($total_price / 100) * $discount_amount;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }



 



}
