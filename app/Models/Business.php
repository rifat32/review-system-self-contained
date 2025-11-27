<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "businesses";

    protected $fillable = [
        "Name",
        "Address",
        "PostCode",
        "OwnerID",
        "Status",
        "Logo",
        "Key_ID",
        "expiry_date",
        "enable_question",
        "About",
        "Webpage",
        "PhoneNumber",
        "EmailAddress",
        "homeText",
        "AdditionalInformation",
        "GoogleMapApi",
        'review_type',
        "show_image",
        'google_map_iframe',
        'Is_guest_user',
        'is_review_silder',
        'review_only',
        "header_image",
        "rating_page_image",
        "placeholder_image",
        "primary_color",
        "secondary_color",
        "client_primary_color",
        "client_secondary_color",
        "client_tertiary_color",
        "user_review_report",
        "guest_user_review_report",
        "pin",
        "time_zone",


        'is_guest_user_overall_review',
        'is_guest_user_survey',
        'is_guest_user_survey_required',
        'is_guest_user_show_stuffs',
        'is_guest_user_show_stuff_image',
        'is_guest_user_show_stuff_name',

        // Registered user fields
        'is_registered_user_overall_review',
        'is_registered_user_survey',
        'is_registered_user_survey_required',
        'is_registered_user_show_stuffs',
        'is_registered_user_show_stuff_image',
        'is_registered_user_show_stuff_name',

        'enable_ip_check',
        'enable_location_check',
        'latitude',
        'longitude',
        'review_distance_limit',
        'threshold_rating',
        'review_labels',

        // 
        'guest_survey_id',
        'registered_user_survey_id'


    ];

    protected $casts = [
        'review_labels' => 'array',
    ];

    protected $hidden = [
        "pin",
        "STRIPE_KEY",
        "STRIPE_SECRET"

    ];



    public function times()
    {
        return $this->hasMany(BusinessDay::class, 'business_id', 'id');
    }


    public function customers()
    {
        return $this->belongsToMany(User::class, "review_news", 'business_id', 'user_id');
    }




    public function owner()
    {
        return $this->hasOne(User::class, 'id', 'OwnerID');
    }


    public function guest_survey()
    {
        return $this->belongsTo(Survey::class, 'guest_survey_id', 'id');
    }

    public function registered_user_survey()
    {
        return $this->belongsTo(Survey::class, 'registered_user_survey_id', 'id');
    }

    /**
     * Scope a query to filter businesses based on search parameters
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query)
    {
        // Enhanced search across multiple fields
        if (request()->filled('search_key')) {
            $searchTerm = request()->input('search_key');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('Name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('Address', 'like', '%' . $searchTerm . '%')
                    ->orWhere('PostCode', 'like', '%' . $searchTerm . '%')
                    ->orWhere('Status', 'like', '%' . $searchTerm . '%')
                    ->orWhere('About', 'like', '%' . $searchTerm . '%')
                    ->orWhere('PhoneNumber', 'like', '%' . $searchTerm . '%')
                    ->orWhere('EmailAddress', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter by status
        if (request()->filled('Status')) {
            $query->where('Status', request()->input('Status'));
        }

        // Filter by owner ID
        if (request()->filled('owner_id')) {
            $query->where('OwnerID', request()->input('owner_id'));
        }

        // Filter by active status (not deleted)
        if (request()->boolean('active_only')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    /**
     * Scope a query to filter businesses for client-facing views
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterClients($query)
    {
        // Apply sorting if provided
        if (request()->filled('sort_by') && request()->filled('sort_type')) {
            $query->orderBy(request()->input('sort_by'), request()->input('sort_type'));
        }

        // Search by business name
        if (request()->filled('search_key')) {
            $searchTerm = request()->input('search_key');
            $query->where('Name', 'like', '%' . $searchTerm . '%');
        }

        // Select only necessary fields for client view
        $query->select(
            'id',
            'Name',
            'header_image',
            'rating_page_image',
            'placeholder_image',
            'Logo',
            'Address'
        );

        return $query;
    }
}
