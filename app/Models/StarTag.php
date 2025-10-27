<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StarTag extends Model
{
    use HasFactory;
    protected $fillable = [
        "tag_id",
        "star_id",
        "question_id",
     ];
     public function tag() {
        return $this->hasOne(Tag::class,'id','tag_id');
    }
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
