<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, Billable;


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
        "job_title"
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

    public function business()
    {
        return $this->hasMany(Business::class, "OwnerID", "id");
    }

    public function feedbacks()
    {
        return $this->hasMany(ReviewNew::class, 'user_id', 'id');
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
            ->whereHas('roles', fn($r) => $r->where('name', 'staff'))
            ->when(request()->filled('search_key'), function ($qq) {
                $s = request()->input('search_key');
                $qq->where(function ($w) use ($s) {
                    $w->where('first_Name', 'like', "%$s%")
                        ->orWhere('last_Name', 'like', "%$s%");
                });
            });
    }
}
