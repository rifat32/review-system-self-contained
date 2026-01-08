<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationRule extends Model
{
    
      /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'applies_to',
        'main_category',
        'sub_category',
        'condition_json',
        'recommendation_template',
        'priority',
        'confidence_required',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'condition_json' => 'array',
        'priority' => 'integer',
    ];
    
    public function recommendations()
{
    return $this->hasMany(Recommendation::class, 'rule_id');
}

}
