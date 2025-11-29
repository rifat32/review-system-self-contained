<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "business_id",
        "show_in_guest_user",
        "show_in_user",
        'order_no'
    ];


    // Survey
    public function questions()
    {
        return $this->belongsToMany(Question::class, 'survey_questions', 'survey_id', 'question_id');
    }
}
