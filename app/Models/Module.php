<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "is_enabled",
        'created_by'
    ];

    public function business_modules()
    {
        return $this->hasMany(BusinessModule::class, "module_id", "id");
    }
}
