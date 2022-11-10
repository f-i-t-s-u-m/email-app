<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    
    protected $table="vendors";

    protected $fillable=[
        'name', 'logo_url'
    ];
    
   public function getLogoUrlAttribute($value)
   {
    
        if($value == null)
       {
           return asset('images/vendors/default.jpg');
       }
       return asset($value);
       
   }
}
