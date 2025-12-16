<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

        protected $appends = ['is_subscribed'];

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
        'is_review_slider',
        'review_only',
        'is_branch',
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
  private function isTrailDateValid($trail_end_date)
{
    // If date is null, empty, or zero-date → treat as NOT expired
    if (
        empty($trail_end_date) ||
        $trail_end_date === '0000-00-00 00:00:00' ||
        $trail_end_date === '0000-00-00'
    ) {
        return true;
    }

    try {
        $parsedDate = Carbon::parse($trail_end_date)->endOfDay();
    } catch (\Exception $e) {
        // If parsing fails, assume not expired
        return true;
    }

    // Valid if today or future
    return !$parsedDate->isPast();
}

     public function getIsSubscribedAttribute($value)
    {
        $user = auth()->user();
        if (empty($user)) {
            return 0;
        }
        // Check for self-registered businesses
        if ($this->is_self_registered_businesses??0) {
            $validTrailDate = $this->isTrailDateValid($this->trail_end_date);
            $latest_subscription = $this->current_subscription;

            // If no valid subscription and no valid trail date, return 0
            if (!$this->isValidSubscription($latest_subscription) && !$validTrailDate) {
                return 0;
            }
        } else {
            // For non-self-registered businesses
            // If the trail date is empty or invalid, return 0
            if (!$this->isTrailDateValid($this->expiry_date)) {
                return 0;
            }
        }

        return 1;
    }

     private function isValidSubscription($subscription)
    {
        if (!$subscription) {
            return false;
        } // No subscription

        // Return false if start_date or end_date is empty
        if (empty($subscription->start_date) || empty($subscription->end_date)) {
            return false;
        }


        $startDate = Carbon::parse($subscription->start_date)->startOfDay();
        $endDate = Carbon::parse($subscription->end_date)->endOfDay();
        $today = Carbon::today(); // Get today's date (start of day)

        // Return false if the subscription hasn't started
        if ($startDate->isFuture()) {
            return false;
        };

        // Return false if the subscription has expired (end_date is before today)
        if ($endDate->isPast() && !$endDate->isSameDay($today)) {
            return false;
        };

        return true;
    }

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

    public function branches()
    {
        return $this->hasMany(Branch::class);
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
