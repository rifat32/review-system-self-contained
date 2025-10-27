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


        'is_customer_order',
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
        "is_customer_schedule_order",
        "time_zone"
    ];

    


    


    public function times() {
        return $this->hasMany(BusinessDay::class,'business_id','id');
    }


    public function customers(){
        return $this->belongsToMany(User::class, "orders",'business_id', 'customer_id');
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
