<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Question",
 *     type="object",
 *     title="Question",
 *     description="Review question model",
 *     required={"question", "is_active"}
 * )
 */

class Question extends Model
{

    /**
     * @OA\Property(property="id", type="integer", example=5)
     * @OA\Property(property="question", type="string", example="How was your experience?")
     * @OA\Property(property="business_id", type="integer", nullable=true, example=3)

     * @OA\Property(property="is_active", type="boolean", example=true)
     * @OA\Property(property="type", type="string", enum={"star","emoji","numbers","heart"}, example="star")
     * @OA\Property(property="is_default", type="boolean", example=false)
     * @OA\Property(property="is_overall", type="boolean", example=false)
     * @OA\Property(property="show_in_user", type="boolean", example=true)
     * @OA\Property(property="show_in_guest_user", type="boolean", example=true)
     * @OA\Property(property="survey_name", type="string", nullable=true, example="Post-Service Survey")
     * @OA\Property(property="created_at", type="string", format="date-time", example="2025-04-05T10:00:00Z")
     * @OA\Property(property="updated_at", type="string", format="date-time", example="2025-04-05T12:30:00Z")
     *
     * @OA\Property(
     *     property="question_category",
     *     type="object",
     *     nullable=true,
     *     @OA\Property(property="id", type="integer", example=1),
     *     @OA\Property(property="title", type="string", example="Staff"),
     *     @OA\Property(property="description", type="string", example="Staff-related questions")
     * )
     * @OA\Property(
     *     property="surveys",
     *     type="array",
     *     @OA\Items(
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=2),
     *         @OA\Property(property="name", type="string", example="Checkout Survey"),
     *         @OA\Property(property="order_no", type="integer", example=1)
     *     )
     * )
     */
    use HasFactory;
    protected $fillable = [
        "question",
        "business_id",
        "is_default",
        "is_active",
        "show_in_guest_user",
        "show_in_user",
        'survey_name',
        "type",
        "order_no",
        "is_overall",
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'show_in_guest_user' => 'boolean',
        'show_in_user' => 'boolean',
        'is_overall' => 'boolean'
    ];




    //
    const QUESTION_TYPES = [
        'STAR'   => 'star',
        'EMOJI'  => 'emoji',
        'NUMBERS' => 'numbers',
        'HEART'  => 'heart',
    ];


    // public function tags() {
    //     return $this->hasMany(StarTagQuestion::class,'question_id','id');
    // }


   // Change from belongsTo to belongsToMany
    public function question_sub_categories()
    {
        return $this->belongsToMany(
            QuestionQuestionSubCategory::class,
            'question_question_sub_categories',
            'question_id',
            'question_sub_category_id'
        );
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($question) {
            if (!$question->order_no) {
                $question->order_no = static::max('order_no') + 1;
            }
        });
    }
    public function question_stars()
    {
        return $this->hasMany(QuestionStar::class, 'question_id', 'id');
    }



    public function review_values()
    {
        return $this->hasMany(ReviewValueNew::class, 'question_id', 'id');
    }

    // Question
    public function surveys()
    {
        return $this->belongsToMany(Survey::class, 'survey_questions', 'question_id', 'survey_id');
    }




    public function scopeFilterByOverall($query, $is_overall)
    {
        return $query->when(isset($is_overall), function ($q) use ($is_overall) {
            $q->whereHas('review_values', function ($q2) use ($is_overall) {
                $q2->filterByOverall($is_overall);
            });
        });
    }

    /**
     * Filter questions based on user role and request parameters
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \App\Models\User $user
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterForUser($query, $user, $request)
    {
        // Apply status filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }



        if ($request->has('is_staff')) {
            $query
                ->whereHas("question_category", function ($q) {
                    $q->where([
                        'question_categories.title' => 'Staff',
                        'question_categories.is_active' => 1,
                        'question_categories.is_default' => 1,
                        'question_categories.business_id' => null,
                    ]);
                });
        }

        if ($user->hasRole('superadmin')) {
            // Superadmin can see all questions
            if ($request->has('business_id')) {
                $query->where('business_id', $request->integer('business_id'));
            }
        } else {
            // Business owner: get default questions + their business questions
            $query->where(function ($q) use ($user) {
                $q->whereNull('business_id') // default questions
                    ->orWhereIn('business_id', $user->businesses()->pluck('id')); // their businesses
            });
        }

        return $query;
    }
}
