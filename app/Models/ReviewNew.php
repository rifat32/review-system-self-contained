<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewNew extends Model
{
    use HasFactory;
    protected $fillable = [
        'description',
        'business_id',
        'rate',
        'user_id',
        'comment',
        'guest_id'
        // "question_id",
        // 'tag_id' ,
        // 'star_id',

    ];
    // public function question() {
    //     return $this->hasOne(Question::class,'id','question_id');
    // }
    // public function tag() {
    //     return $this->hasOne(Question::class,'id','tag_id');
    // }
    public function value() {
        return $this->hasMany(ReviewValueNew::class,'review_id','id');
    }



    public function business() {
        return $this->hasOne(Business::class,'id','business_id');
    }
    public function user() {
        return $this->hasOne(User::class,'id','user_id');
    }
    public function guest_user() {
        return $this->hasOne(GuestUser::class,'id','guest_id');
    }
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // In your QuestionValue model (or the model that has 'value' relation)
public function scopeFilterByOverall($query, $is_overall)
{
    return $query->when(isset($is_overall), function ($q) use ($is_overall) {
        $q->whereHas('value', function ($q2) use ($is_overall) {
            $q2->whereHas('question', function ($q3) use ($is_overall) {
                $q3->where('is_overall', $is_overall ? 1 : 0);
            });
        });
    });
}


}
