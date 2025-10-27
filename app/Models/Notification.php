<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    protected $fillable = [
        "reciever_id",
        "sender_id",
        "business_id",
        'sender_type',
        "message",
        "status",
        "title"
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
