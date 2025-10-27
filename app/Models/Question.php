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
}
