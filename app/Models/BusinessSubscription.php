<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'service_plan_id',
        'start_date',
        'end_date',
        'status',
        'amount',
        'paid_at',
        "transaction_id",
        "openai_token_limit",
        "stripe_id",
        "stripe_status",
        "stripe_price",
        "stripe_plan",
        "quantity",
        "trial_ends_at",
        "ends_at"
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, "business_id", "id");
    }

    public function service_plan()
    {
        return $this->belongsTo(ServicePlan::class, "service_plan_id", "id");
    }
}
