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
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
