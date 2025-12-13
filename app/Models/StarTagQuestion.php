<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StarTagQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag_id',
        'star_id',
        "question_id",
        "is_default"
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tag()
    {
        return $this->hasOne(Tag::class, 'id', 'tag_id');
    }
    public function question()
    {
        return $this->hasOne(Question::class, 'id', 'question_id');
    }
    public function star()
    {
        return $this->hasOne(Star::class, 'id', 'star_id');
    }
}
