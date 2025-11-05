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
        "type",
        "business_id",
        "date_of_birth"
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
}
