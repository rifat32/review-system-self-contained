<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Star extends Model
{
    use HasFactory;
    protected $fillable = [
        'value',
    ];
    public function star_tags() {
        return $this->hasMany(StarTag::class,'star_id','id');
    }

    public function review_values() {
        return $this->hasMany(ReviewValueNew::class,'star_id','id');
    }

    public function scopeFilterByOverall($query, $is_overall)
{
    return $query->when(isset($is_overall), function ($q) use ($is_overall) {
        $q->whereHas('review_values', function ($q2) use ($is_overall) {
            $q2->whereHas('question', function ($q3) use ($is_overall) {
                $q3->where('is_overall', $is_overall ? 1 : 0);
            });
        });
    });
}
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
