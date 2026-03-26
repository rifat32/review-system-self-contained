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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($qrCodeSetting) {
            // Set default qrStyling if not provided
            if (empty($qrCodeSetting->qrStyling)) {
                $qrCodeSetting->qrStyling = [
                    'dotsOptions' => [
                        'type' => 'rounded',
                        'color' => '#0d7ff2',
                    ],
                    'cornersSquareOptions' => [
                        'type' => 'extra-rounded',
                        'color' => '#0d7ff2',
                    ],
                    'cornersDotOptions' => [
                        'type' => 'dot',
                        'color' => '#0d7ff2',
                    ],
                    'backgroundOptions' => [
                        'color' => '#ffffff',
                        'margin' => 10,
                    ],
                    'image' => '',
                    'imageOptions' => [
                        'hideBackgroundDots' => true,
                        'imageSize' => 0.4,
                        'margin' => 10,
                    ],
                ];
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
