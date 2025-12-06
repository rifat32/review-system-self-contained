<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionStar extends Model
{
    use HasFactory;
    protected $fillable = [
        "question_id",
        "star_id",
    ];
    public function star()
    {
        return $this->hasOne(Star::class, 'id', 'star_id');
    }
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
