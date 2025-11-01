<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;
    protected $fillable = [
       "question",
       "business_id",
       "is_default",
       "is_active",
       "type"
    ];

    // public function tags() {
    //     return $this->hasMany(StarTagQuestion::class,'question_id','id');
    // }
    public function question_stars() {
        return $this->hasMany(QusetionStar::class,'question_id','id');
    }
    protected $hidden = [
        'created_at',
        'updated_at',
    ];


     public function review_values() {
        return $this->hasMany(ReviewValueNew::class,'question_id','id');
    }

    public function scopeFilterByOverall($query, $is_overall)
{
    return $query->when(isset($is_overall), function ($q) use ($is_overall) {
        $q->whereHas('review_values', function ($q2) use ($is_overall) {
            $q2->whereHas('question', function ($q3) use ($is_overall) {
                $q3->where('is_overall', $is_overall ? 1 : 0);
            });
        });
    });
}
}
