<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;
    protected $fillable = [
        "receiver_id",
        "sender_id",
        "business_id",
        "sender_type",
        "message",
        "status",
        "title",
        "type",
        "read_at",
        "link",
        "priority",
        "entity_id",
        "entity_ids",
    ];

    protected $casts = [
        "entity_ids" => "array",
        "read_at" => "datetime",
    ];


    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, "receiver_id", "id");
    }

    public function scopeNotificationFilters($query)
    {
        if (request()->filled('status')) {
            $status = request()->input('status');
            if ($status === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($status === 'unread') {
            $query->whereNull('read_at');
            }
        }


        return $query;
    }
}
