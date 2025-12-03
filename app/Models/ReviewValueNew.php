<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewValueNew extends Model
{
    use HasFactory;
    protected $fillable = [
        "question_id",
        'tag_id' ,
        'star_id',
        'review_id',

    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function business() {
        return $this->hasOne(Business::class,'id','business_id');
    }

    public function review() {
        return $this->hasOne(ReviewNew::class,'id','review_id');
    }

    public function question() {
        return $this->hasOne(Question::class,'id','question_id');
    }

    public function tag() {
        return $this->belongsTo(Tag::class, 'tag_id', 'id');
    }


public function scopeFilterByOverall($query, $is_overall)
{
    return $query->when(isset($is_overall), function ($q) use ($is_overall) {
  
            $q->whereHas('review', function ($q2) use ($is_overall) {
                $q2->where('is_overall', $is_overall ? 1 : 0);
            });
       
    });
}

public function scopeFilterByBusiness($query, $businessId)
{
    return $query->whereHas('review', function ($query) use ($businessId) {
        $query->where('status', 'published')
              ->when(!request()->user()->hasRole('superadmin'), function ($q) use ($businessId) {
                  $q->where('business_id', $businessId);
              });
    });
}


}
