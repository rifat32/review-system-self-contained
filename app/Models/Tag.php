<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        "business_id",
        "is_default",
        "is_active"
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        "business_id",
    ];


     public function review_values() {
        return $this->hasMany(ReviewValueNew::class,'tag_id','id');
    }

   public function scopeFilterByOverall($query, $is_overall)
{
    return $query->when(isset($is_overall), function ($q) use ($is_overall) {
        $q->whereHas('review_values', function ($q2) use ($is_overall) {
             $q2->filterByOverall($is_overall);
        });
    });
}




}
