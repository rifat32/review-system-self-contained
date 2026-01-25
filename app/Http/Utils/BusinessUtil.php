<?php

namespace App\Http\Utils;

use App\Models\Business;
use Exception;

trait BusinessUtil
{

    public function businessOwnerCheck($business_id, $strict = FALSE)
    {

        $business = Business::where('id', $business_id)
            ->when(
                $strict || !request()->user()->hasRole('superadmin'),
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            // ->where('id', auth()->user()->business_id)
                            // ->orWhere('created_by', auth()->user()->id)
                            ->orWhere('OwnerID', auth()->user()->id)
                            // ->orWhere('reseller_id', auth()->user()->id)
                        ;
                    });
                },
            )
            ->first();


        if (empty($business)) {
            throw new Exception("you are not the owner of the business or the requested business does not exist.", 401);
        }
        return $business;
    }
}
