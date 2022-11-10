<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingDetail extends Model
{

    protected $table = "shipping_details";
    protected $dates = ['time'];
    protected $fillable = [
        'order_id',
        'place',
        'time',
        'status'
    ];
}
