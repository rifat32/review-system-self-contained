<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'order_no',
        'is_active'
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

    public function reviews() {
        return $this->hasMany(ReviewNew::class, 'survey_id', 'id');
    }
     // Business Services (NEW RELATIONSHIP)
    public function business_services()
    {
        return $this->belongsToMany(
            BusinessService::class,
            'business_service_surveys',
            'survey_id',
            'business_service_id'
        )->withTimestamps();
    }

    // FILTER SCOPE
    public function scopeFilter(Builder $query)
    {

        // Apply filters
        if (request()->filled('search_key')) {
            $search_key = request()->search_key;
            $query->where('name', 'like', '%' . $search_key . '%');
        }

        if (request()->filled('start_date')) {
            $query->whereDate('created_at', '>=', request()->start_date);
        }

        if (request()->filled('end_date')) {
            $query->whereDate('created_at', '<=', request()->end_date);
        }


        if (request()->filled('is_active') && request()->is_active !== '') {
            $query->where('is_active', request()->is_active);
        }

        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $query->whereBetween('created_at', [
                request()->start_date,
                request()->end_date
            ]);
        }

        return $query;
    }
}
