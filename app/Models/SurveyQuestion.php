<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="SurveyQuestion",
 *     type="object",
 *     title="SurveyQuestion",
 *     description="Survey question pivot model",
 *     required={"survey_id", "question_id"}
 * )
 */
class SurveyQuestion extends Model
{
    /**
     * @OA\Property(property="id", type="integer", example=1)
     * @OA\Property(property="survey_id", type="integer", example=2)
     * @OA\Property(property="question_id", type="integer", example=5)
     * @OA\Property(property="order_no", type="integer", example=1)
     * @OA\Property(property="created_at", type="string", format="date-time")
     * @OA\Property(property="updated_at", type="string", format="date-time")
     *
     * @OA\Property(
     *     property="survey",
     *     ref="#/components/schemas/Survey"
     * )
     * @OA\Property(
     *     property="question",
     *     ref="#/components/schemas/Question"
     * )
     */
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
