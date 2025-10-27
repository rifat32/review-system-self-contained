<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $connection = 'logs';

    protected $fillable = [
        "api_url",
        "token",
        "fields",
        "user",
        "user_id",
        "activity",
        "description",
        "ip_address",
        "request_method",
        "device",
        "is_error",
        "message",
        "status_code"

    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->setConnection('mysql');
    }
}
