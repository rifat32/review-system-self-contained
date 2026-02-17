<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public const USER_ROLE = [
        'SUPER_ADMIN' => 'superadmin',
        'BUSINESS_OWNER' => 'business_owner',
        'BRANCH_MANAGER' => 'branch_manager',
        'BUSINESS_STAFF' => 'business_staff',
        'CUSTOMER' => 'customer',
    ];

    const superAdmin = 'superadmin';
    const businessOwner = 'business_owner';
    const branchManager = 'branch_manager';
    const businessStaff = 'business_staff';
    const customer = 'customer';

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
        "email_verify_token",
        "email_verify_token_expires",
        "is_active"
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
        "login_attempts",
        "last_failed_login_attempt_at"
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean'
    ];


    // app/Models/User.php
    public function getDefaultBranchIdAttribute()
    {
        if ($this->hasRole('branch_manager')) {
            return $this->branch?->branch_id ?? null;
        }

        if ($this->hasRole('business_owner')) {
            return $this->business?->default_branch_id ?? null;
        }

        return null;
    }

    /**
     * Get the user's primary role as a single object
     * Returns the first role assigned to the user
     *
     * @return \Spatie\Permission\Models\Role|null
     */
    public function role()
    {
        return $this->roles()->first();
    }

    public function ownedBusiness(): HasOne
    {
        return $this->hasOne(Business::class, "OwnerID", "id");
    }

    public function business(): HasOne
    {
        return $this->hasOne(Business::class, "id", "business_id");
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


    public function branch(): HasOne
    {
        return $this->hasOne(BranchMember::class, 'user_id', 'id')
            ->where('is_active', true);
    }

    public function branches()
    {
        return $this->hasManyThrough(
            Branch::class,       // Target model
            BranchMember::class, // Intermediate model
            'user_id',           // Foreign key on intermediate table
            'id',               // Foreign key on target table
            'id',               // Local key on this model
            'branch_id'         // Local key on intermediate table
        );
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

        return $query->when(request()->role, function ($query) {
            $query->whereHas('roles', function ($query) {
                $query->where('name', request()->role);
            });
        }, function ($query) {
            $query->whereHas('roles', fn($r) => $r->whereIn('name', ['business_staff', 'branch_manager']));
        });
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
            ->when(request()->role, function ($query) {
                $query->whereHas('roles', function ($query) {
                    $query->where('name', request()->role);
                });
            }, function ($query) {
                $query->whereHas('roles', fn($r) => $r->whereIn('name', ['business_staff', 'branch_manager']));
            })
            ->when(request()->branch_id, function ($query) {
                $query->whereHas('branch', function ($query) {
                    $query->where('branch_id', request()->branch_id);
                });
            })
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
            // Filter by rating if provided (using calculated rating from review_value_news)
            ->when(request()->filled('rating'), function ($q) {
                $q->whereHas('feedbacks', function ($query) {
                    $query->whereExists(function ($subQuery) {
                        $subQuery->selectRaw('1')
                            ->from('review_value_news as rvn')
                            ->join('stars as s', 'rvn.star_id', '=', 's.id')
                            ->whereColumn('rvn.review_id', 'review_news.id')
                            ->groupBy('rvn.review_id')
                            ->havingRaw('ROUND(AVG(s.value), 0) = ?', [request()->input('rating')]);
                    });
                });
            })
            // Filter by review date range
            ->when(request()->filled('review_start_date') && request()->filled('review_end_date'), function ($q) {
                $q->whereHas('feedbacks', function ($query) {
                    $query->whereBetween('review_news.created_at', [
                        request()->input('review_start_date'),
                        request()->input('review_end_date') . ' 23:59:59'
                    ]);
                });
            })
            // Filter by review keyword
            ->when(request()->filled('review_keyword'), function ($q) {
                $q->whereHas('feedbacks', function ($query) {
                    $query->where('review_news.comment', 'like', '%' . request('review_keyword') . '%');
                });
            })
            // Filter by frequency visit (based on review count)
            ->when(request()->filled('frequency_visit'), function ($q) {
                $frequency = request()->input('frequency_visit');

                if ($frequency == "New") {
                    // New customers: exactly 1 review
                    $q->has('feedbacks', '=', 1);
                } elseif ($frequency == "Regular") {
                    // Regular customers: 2-5 reviews
                    $q->has('feedbacks', '>=', 2)->has('feedbacks', '<=', 5);
                } elseif ($frequency == "VIP") {
                    // VIP customers: 6 or more reviews
                    $q->has('feedbacks', '>=', 6);
                }
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
