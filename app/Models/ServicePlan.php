<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "description",
        "set_up_amount",
        'price',
        'duration_months',
        'openai_token_limit',
        'is_active',
        "created_by"
    ];

    public function service_plan_modules()
    {
        return $this->hasMany(ServicePlanModule::class, 'service_plan_id', 'id');
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'service_plan_modules', 'service_plan_id', 'module_id');
    }
}
