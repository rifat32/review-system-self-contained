<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, Billable;

    protected $guard_name = 'api';
    protected $fillable = [
        'first_Name',
        'last_Name',
        'email',
        'password',
        'phone',
        'type',
        "post_code",
        "Address",
        "door_no",
        "business_id",
        "date_of_birth",
        "image",
        "job_title",
        "join_date",
        "skills",
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'pin',
        'password',
        'remember_token',
        'resetPasswordToken',
        'resetPasswordExpires',
        'created_at',
        'updated_at',
        'email_verify_token',
        'email_verify_token_expires',
        'email_verified_at',
        "login_attempts",
        "last_failed_login_attempt_at"
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function business(): HasOne
    {
        return $this->hasOne(Business::class, "OwnerID", "id");
    }

    public function businesses()
    {
        return $this->hasMany(Business::class, "OwnerID", "id");
    }

    public function getNameAttribute()
    {
        return "{$this->first_Name} {$this->last_Name}";
    }

    public function feedbacks()
    {
        return $this->hasMany(ReviewNew::class, 'user_id', 'id');
    }

    public function staffReviews()
    {
        return $this->hasMany(ReviewNew::class, 'staff_id', 'id');
    }

    /**
     * Scope a query to filter users based on search criteria
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query)
    {
        if (request()->filled('search_key')) {
            $searchTerm = request()->search_key;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_Name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('last_Name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('email', 'like', '%' . $searchTerm . '%')
                    ->orWhere('phone', 'like', '%' . $searchTerm . '%')
                    ->orWhere('type', 'like', '%' . $searchTerm . '%')
                    ->orWhere('post_code', 'like', '%' . $searchTerm . '%')
                    ->orWhere('Address', 'like', '%' . $searchTerm . '%')
                    ->orWhere('door_no', 'like', '%' . $searchTerm . '%');
            });
        }

        return $query;
    }

    /**
     * Scope a query to filter staff users for a specific business
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $businessId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterStaff($query, $businessId)
    {
        return $query->where('business_id', $businessId)
            ->whereHas('roles', fn($r) => $r->where('name', 'business_staff'))
            ->when(request()->filled('search_key'), function ($qq) {
                $s = request()->input('search_key');
                $qq->where(function ($w) use ($s) {
                    $w->where('first_Name', 'like', "%$s%")
                        ->orWhere('last_Name', 'like', "%$s%");
                });
            });
    }

    /**
     * Scope a query to filter customers based on various criteria
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterCustomers($query)
    {
        return $query
            // Filter by rating if provided
            ->when(request()->filled('rating'), function ($q) {
                $q->whereHas('feedbacks', function ($query) {
                    $query->where('review_news.rate', request()->input('rating'));
                });
            })
            // Filter by review date range
            ->when(request()->filled('review_start_date') && request()->filled('review_end_date'), function ($q) {
                $q->whereHas('feedbacks', function ($query) {
                    $query->whereBetween('review_news.updated_at', [
                        request()->input('review_start_date'),
                        request()->input('review_end_date')
                    ]);
                });
            })
            // Filter by review keyword
            ->when(request()->filled('review_keyword'), function ($q) {
                $q->whereHas('feedbacks', function ($query) {
                    $query->where('review_news.comment', 'like', '%' . request('review_keyword') . '%');
                });
            })
            // Filter by frequency visit
            ->when(request()->filled('frequency_visit'), function ($q) {
                $frequency = request()->input('frequency_visit');
                $query_param = '='; // Default value for the comparison operator.
                $min_count = 1;     // Minimum booking count.
                $max_count = 1;     // Maximum booking count (for regular customers).

                if ($frequency == "New") {
                    // For new customers, the count should be exactly 1.
                    $query_param = '=';
                    $min_count = 1;
                    $max_count = 1;
                } elseif ($frequency == "Regular") {
                    // For regular customers, the count should be between 2 and 5.
                    $query_param = 'BETWEEN';
                    $min_count = 2;
                    $max_count = 5;
                } elseif ($frequency == "VIP") {
                    // For VIP customers, the count should be 5 or more.
                    $query_param = '>=';
                    $min_count = 5;
                    $max_count = null; // No upper limit for VIP.
                } else {
                    // Default case or other logic can be applied here.
                    $query_param = '=';
                    $min_count = 1;
                    $max_count = 1;
                }

                // Apply the frequency filter logic here if needed
                // Note: This might need adjustment based on your booking/review relationship
            })
            // Filter by name
            ->when(request()->filled('name'), function ($query) {
                $name = request()->input('name');
                return $query->where(function ($subQuery) use ($name) {
                    $subQuery->where("first_Name", "like", "%" . $name . "%")
                        ->orWhere("last_Name", "like", "%" . $name . "%");
                });
            })
            // Filter by email
            ->when(request()->filled('email'), function ($query) {
                return $query->where('users.email', 'like', '%' . request()->input('email') . '%');
            })
            // Filter by phone
            ->when(request()->filled('phone'), function ($query) {
                return $query->where('users.phone', 'like', '%' . request()->input('phone') . '%');
            })
            // Filter by search key (general search)
            ->when(request()->filled('search_key'), function ($query) {
                $term = request()->input('search_key');
                return $query->where(function ($query) use ($term) {
                    $query->where('first_Name', 'like', '%' . $term . '%')
                        ->orWhere('last_Name', 'like', '%' . $term . '%')
                        ->orWhere('email', 'like', '%' . $term . '%')
                        ->orWhere('phone', 'like', '%' . $term . '%');
                });
            })
            // Filter by creation date range
            ->when(request()->filled('start_date'), function ($query) {
                return $query->where('users.created_at', ">=", request()->input('start_date'));
            })
            ->when(request()->filled('end_date'), function ($query) {
                return $query->where('users.created_at', "<=", (request()->input('end_date') . ' 23:59:59'));
            });
    }
}
