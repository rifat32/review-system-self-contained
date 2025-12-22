<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        "survey_id",
        "question_id",
        "order_no",
    ];

    // AUTO SET ORDER NO
    protected static function boot()
    {
        return parent::boot();
        static::creating(function ($survey_question) {
            if (!$survey_question->order_no) {
                $survey_question->order_no = static::max('order_no') + 1;
            }
        });
    }

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'survey_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
