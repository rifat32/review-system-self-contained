<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrCodeSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'slug',
        'qrStyling',
    ];

    protected $casts = [
        'qrStyling' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
