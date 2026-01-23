<?php

namespace App\Models;

use Carbon\Carbon;
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
        'is_active',
        'last_recommendation_at',
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
        'registered_user_survey_id',
        'default_color_threshold',
        'default_branch_id',
        'has_rule_management',
        'is_treat_manager_as_staff',
        'openai_token_limit',
        'service_plan_id'

    ];

    protected $casts = [
        'review_labels' => 'array',
        'default_color_threshold' => 'array',
        'is_treat_manager_as_staff' => 'boolean',
    ];

    protected $hidden = [
        "pin"
    ];



    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($business) {
            $folder_path = "business_{$business->OwnerID}/business_{$business->id}";
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($folder_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->deleteDirectory($folder_path);
            }
        });
    }

    public function businessModules()
    {
        return $this->hasMany(BusinessModule::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(BusinessSubscription::class);
    }

    public function current_subscription()
    {
        return $this->hasOne(BusinessSubscription::class)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'desc');
    }



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

    public function getIsSubscribedAttribute(): bool
    {
        // 1. Check legacy trail/expiry logic
        if ($this->expiry_date && Carbon::parse($this->expiry_date)->isFuture()) {
            return true;
        }

        // 2. Check new subscription system
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->exists();
    }

    /**
     * Check if business has reached its OpenAI token limit
     */
    public function getIsTokenLimitReachedAttribute(): bool
    {
        $limit = $this->openai_token_limit;

        // -1 means unlimited
        if ($limit === -1) {
            return false;
        }

        // If limit is 0 and not explicitly -1, consider it reached (no quota)
        if ($limit === 0 || $limit === null) {
            return true;
        }

        // Calculate usage for current month (standard fallback)
        $used = \App\Models\OpenAITokenUsage::where('business_id', $this->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_tokens');

        return $used >= $limit;
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

    public function defaultBranch()
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
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
        $period = request()->get('period', 'all_time');

        $dateRange = $period === 'all_time' ? null : getDateRangeByPeriod($period);

        // DATE RANGE FILTER
        if ($dateRange) {
            $query->whereBetween('businesses.created_at', [
                Carbon::parse($dateRange['start'])->startOfDay(),
                Carbon::parse($dateRange['end'])->endOfDay()
            ]);
        }

        // ACTIVE STATUS FILTER
        if (request()->filled('is_active')) {
            $query->where('businesses.is_active', request()->input('is_active'));
        }

        // Enhanced search across multiple fields
        if (request()->filled('search_key')) {
            $searchTerm = request()->input('search_key');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('businesses.Name', 'like', '%' . $searchTerm . '%')
                    // ->orWhere('businesses.Address', 'like', '%' . $searchTerm . '%')
                    // ->orWhere('businesses.PostCode', 'like', '%' . $searchTerm . '%')
                    // ->orWhere('businesses.Status', 'like', '%' . $searchTerm . '%')
                    // ->orWhere('businesses.About', 'like', '%' . $searchTerm . '%')
                    // ->orWhere('businesses.PhoneNumber', 'like', '%' . $searchTerm . '%')
                    ->orWhere('businesses.EmailAddress', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter by status
        if (request()->filled('Status')) {
            $query->where('businesses.Status', request()->input('Status'));
        }

        // Filter by owner ID
        if (request()->filled('owner_id')) {
            $query->where('businesses.OwnerID', request()->input('owner_id'));
        }

        // Filter by active status (not deleted)
        if (request()->boolean('active_only')) {
            $query->whereNull('businesses.deleted_at');
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
