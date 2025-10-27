<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TagReview extends Model
{
    use HasFactory;
    protected $fillable = [
        'review_id',
        "tag_id"
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
