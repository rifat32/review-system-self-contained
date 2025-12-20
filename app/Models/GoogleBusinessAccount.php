<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class GoogleBusinessAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'account_name',
        'type',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the user that owns the account
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all locations for this account
     */
    public function locations()
    {
        return $this->hasMany(GoogleBusinessLocation::class);
    }

    /**
     * Get active locations only
     */
    public function activeLocations()
    {
        return $this->hasMany(GoogleBusinessLocation::class)->where('is_active', true);
    }

    /**
     * Check if the access token is expired
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        return now()->greaterThanOrEqualTo($this->token_expires_at);
    }

    /**
     * Get decrypted access token
     */
    public function getDecryptedAccessToken(): ?string
    {
        try {
            return $this->access_token ? Crypt::decryptString($this->access_token) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get decrypted refresh token
     */
    public function getDecryptedRefreshToken(): ?string
    {
        try {
            return $this->refresh_token ? Crypt::decryptString($this->refresh_token) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted access token
     */
    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Set encrypted refresh token
     */
    public function setRefreshTokenAttribute($value)
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }
}
