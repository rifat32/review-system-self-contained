<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionQuestionSubCategory extends Model
{
    
    protected $table = 'q_q_sub_categories';

    protected $fillable = [
        'question_id',
        'question_sub_category_id',
    ];
}
