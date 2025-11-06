<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "businesses";

    protected $fillable = [
        "Name",
        "Address",
        "PostCode",
        "OwnerID",
        "Status",
        "Logo",
        "Key_ID",
        "expiry_date",
        "enable_question",
        "About",
        "Webpage",
        "PhoneNumber",
        "EmailAddress",
        "homeText",
        "AdditionalInformation",
        "GoogleMapApi",
        'review_type',
        "show_image",
        'google_map_iframe',
        'Is_guest_user',
        'is_review_silder',
        'review_only',
        "header_image",
        "rating_page_image",
        "placeholder_image",
        "primary_color",
        "secondary_color",
        "client_primary_color",
        "client_secondary_color",
        "client_tertiary_color",
        "user_review_report",
        "guest_user_review_report",
        "pin",
        "time_zone",


                'is_guest_user_overall_review',
                'is_guest_user_survey',
                'is_guest_user_survey_required',
                'is_guest_user_show_stuffs',
                'is_guest_user_show_stuff_image',
                'is_guest_user_show_stuff_name',

                // Registered user fields
                'is_registered_user_overall_review',
                'is_registered_user_survey',
                'is_registered_user_survey_required',
                'is_registered_user_show_stuffs',
                'is_registered_user_show_stuff_image',
                'is_registered_user_show_stuff_name',


    ];

    


    


    public function times() {
        return $this->hasMany(BusinessDay::class,'business_id','id');
    }


    public function customers(){
        return $this->belongsToMany(User::class, "review_news",'business_id', 'user_id');
    }




    public function owner() {
        return $this->hasOne(User::class,'id','OwnerID');
    }
    public function table() {
        return $this->hasMany(BusinessTable::class,'id','business_id');
    }
    protected $hidden = [
      "pin",
      "STRIPE_KEY",
      "STRIPE_SECRET"

    ];
}
