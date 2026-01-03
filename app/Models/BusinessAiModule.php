<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessAIModule extends Model
{
    protected $table = 'business_ai_modules';

    protected $fillable = [
        'business_id',
        'language_translation',
        'sentiment_analysis',
        'emotion_detection',
        'abuse_detection',
        'explainability',
        'category_analysis',
        'staff_intelligence',
        'service_unit_intelligence',
        'business_recommendations',
        'alerts',
    ];

    protected $casts = [
        'language_translation' => 'boolean',
        'sentiment_analysis' => 'boolean',
        'emotion_detection' => 'boolean',
        'abuse_detection' => 'boolean',
        'explainability' => 'boolean',
        'category_analysis' => 'boolean',
        'staff_intelligence' => 'boolean',
        'service_unit_intelligence' => 'boolean',
        'business_recommendations' => 'boolean',
        'alerts' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get enabled modules as array
     */
    public function getEnabledModules(): array
    {
        $modules = [];

        // Required modules (always true)
        $modules['language_translation'] = true;
        $modules['sentiment_analysis'] = true;
        $modules['emotion_detection'] = true;
        $modules['abuse_detection'] = true;
        $modules['explainability'] = true;

        // Optional modules (can be disabled)
        $modules['category_analysis'] = $this->category_analysis;
        $modules['staff_intelligence'] = $this->staff_intelligence;
        $modules['service_unit_intelligence'] = $this->service_unit_intelligence;
        $modules['business_recommendations'] = $this->business_recommendations;
        $modules['alerts'] = $this->alerts;

        return $modules;
    }

    /**
     * Check if specific module is enabled
     */
    public function isModuleEnabled(string $module): bool
    {
        return $this->getEnabledModules()[$module] ?? false;
    }

    /**
     * Get default configuration for a business
     */
    // public static function getDefaultForBusiness(int $businessId): array
    // {
    //     return [
    //         'business_id' => $businessId,
    //         'language_translation' => true,
    //         'sentiment_analysis' => true,
    //         'emotion_detection' => true,
    //         'abuse_detection' => true,
    //         'explainability' => true,
    //         'category_analysis' => true,
    //         'staff_intelligence' => true,
    //         'service_unit_intelligence' => true,
    //         'business_recommendations' => true,
    //         'alerts' => true,
    //     ];
    // }
    /**
     * Get default configuration for a business
     */
    public static function getDefaultForBusiness(int $businessId): array
    {
        return [
            'business_id' => $businessId,
            'language_translation' => true,  // Required
            'sentiment_analysis' => true,    // Required
            'emotion_detection' => true,     // Required
            'abuse_detection' => true,       // Required
            'explainability' => true,        // Required

            // Optional modules - DISABLED by default
            'category_analysis' => false,
            'staff_intelligence' => false,
            'service_unit_intelligence' => false,
            'business_recommendations' => false,
            'alerts' => false,
        ];
    }
}
