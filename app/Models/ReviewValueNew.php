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

}
