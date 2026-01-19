<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        "business_id",
        "is_default",
        "is_active"
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        "business_id",
    ];


    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function review_values()
    {
        return $this->belongsToMany(ReviewValueNew::class, 'review_value_tag', 'tag_id', 'review_value_id');
    }


    public function scopeFilterByOverall($query)
    {
        if (request()->filled("is_overall")) {
            $is_overall = request()->boolean("is_overall");
            $query->whereHas('review_values', function ($q2) use ($is_overall) {
                $q2->filterByOverall($is_overall);
            });

        }
        return $query;
    }

    public function scopeFilter(Builder $query): Builder
    {
        $user = request()->user();
        $isSuperAdmin = $user?->hasRole('superadmin') ?? false;

        $businessId = request()->input('business_id');

        // Superadmin: only default tags
        if ($isSuperAdmin) {
            $query->whereNull('business_id')
                ->where('is_default', 1);
        } else {
            // Non-superadmin: business_id required
            if (empty($businessId)) {
                // Force empty result (controller can still return 422 if you prefer)
                return $query->whereRaw('1=0');
            }

            $query->where(function (Builder $q) use ($businessId) {
                $q->where('business_id', (int) $businessId)
                    ->where('is_default', 0);
            })->orWhere(function (Builder $q) {
                $q->whereNull('business_id')
                    ->where('is_default', 1);
            });
        }

        // Optional filter: is_active
        if (request()->filled('is_active')) {
            $val = request()->input('is_active');
            $isActive = in_array($val, ['1', 1, true, 'true'], true) ? 1 : 0;
            $query->where('tags.is_active', $isActive);
        }

        return $query;
    }
}
