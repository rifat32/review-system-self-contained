<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;

    protected $hidden = ['pivot'];

    protected $fillable = [
        "name",
        "business_id",
        "show_in_guest_user",
        "show_in_user",
        'order_no'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($survey) {
            if (!$survey->order_no) {
                $survey->order_no = static::max('order_no') + 1;
            }
        });
    }

    // Survey
    public function questions()
    {
        return $this->belongsToMany(Question::class, 'survey_questions', 'survey_id', 'question_id');
    }
}
