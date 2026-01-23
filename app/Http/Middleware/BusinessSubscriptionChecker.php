<?php

namespace App\Http\Middleware;

use App\Models\BusinessSubscription;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

class BusinessSubscriptionChecker
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if ($user && $user->business) {
            $business = $user->business;

            // Check if business is not active
            if (!$business->is_active) {
                return response()->json(["message" => "Business is not active."], 401);
            }

            // Check if subscription ended
            if (!$business->is_subscribed) {
                // Determine the last active expiry date (legacy or new system)
                $last_expiry = $business->expiry_date;
                if (!$last_expiry) {
                    $latest_subscription = $business->subscriptions()->orderBy('end_date', 'desc')->first();
                    $last_expiry = $latest_subscription?->end_date;
                }

                // If user is not the business owner, apply grace period logic if applicable
                if (!$user->hasRole("business_owner") && $last_expiry) {
                    $days_since_expiry = Carbon::parse($last_expiry)->diffInDays(today(), false);

                    if ($days_since_expiry > 10) {
                        return response()->json(["message" => "Your grace period has ended."], 401);
                    }
                } else {
                    // Business owner or no expiry info – deny immediately
                    return response()->json(["message" => "Your subscription has ended."], 401);
                }
            }
        }

        return $next($request);
    }
}
