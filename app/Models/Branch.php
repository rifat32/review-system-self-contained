<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $hidden = ['pivot'];
    protected $appends = ['avg_rating'];

    protected $fillable = [
        'business_id',
        'name',
        'address',
        'street',
        'door_no',
        'city',
        'country',
        'postcode',
        'phone',
        'email',
        'is_active',
        'is_default',
        'is_geo_enabled',
        'branch_code',
        'lat',
        'long',
        'manager_id',
    ];

    public function getAvgRatingAttribute()
    {
        return $this->reviews()
            ->join('review_value_news as rvn', 'rvn.review_id', '=', 'review_news.id')
            ->join('stars as s', 's.id', '=', 'rvn.star_id')
            ->selectRaw('ROUND(AVG(s.value), 1) as avg_rating')
            ->value('avg_rating') ?? 0;
    }


    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ReviewNew::class, 'branch_id', 'id');
    }

    public function scopeFilterByDateRange($query, bool $isComparisonDateRange = false)
    {
        if ($isComparisonDateRange) {
            $query->when(request()->filled('period'), function ($query) {
                $dateRange = getDateRangeByPeriod(request()->input('period'));
                $startDate = $dateRange['start']->subDays($dateRange['daysOffset'])->startOfDay();
                $endDate = $dateRange['end']->subDays($dateRange['daysOffset'])->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            });
        } else {
            $query->when(request()->filled('start_date'), function ($q) {
                $q->whereDate('branches.created_at', '>=', Carbon::parse(request()->input('start_date'))->startOfDay());
            })
                ->when(request()->filled('end_date'), function ($q) {
                    $q->whereDate('branches.created_at', '<=', Carbon::parse(request()->input('end_date'))->endOfDay());
                })
                ->when(request()->filled('period'), function ($q) {
                    $dateRange = getDateRangeByPeriod(request()->input('period'));
                    $q->whereBetween('branches.created_at', [$dateRange['start'], $dateRange['end']]);
                });
        }



        return $query;
    }

    public function scopeBranchGlobalFilters($query)
    {
        $authUser = auth()->user();

        if ($authUser->default_branch_id) {
            $query->where('id', $authUser->default_branch_id);
        }

        return $this->filters($query);
    }


    public function scopeFilters($query)
    {
        // Apply search filter
        if (request()->has('search_key') && !empty(request()->search_key)) {
            $searchKey = request()->search_key;
            $query->where(function ($q) use ($searchKey) {
                $q->where('name', 'like', '%' . $searchKey . '%')
                    ->orWhere('branch_code', 'like', '%' . $searchKey . '%');
            });
        }

        if (request()->filled('is_manager_assigned')) {
            if (request()->boolean("is_manager_assigned")) {
                $query->whereNotNull('manager_id');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('manager_id');
                    if (request()->filled('ignore_id')) {
                        $q->orWhere('id', request()->ignore_id);
                    }
                });
            }
        }

        if(request()->filled('is_active')) {
            $query->where('is_active', request()->boolean("is_active"));
        }

        if(request()->filled('is_default')) {
            $query->where('is_default', request()->boolean("is_default"));
        }

        return $query;
    }

}
