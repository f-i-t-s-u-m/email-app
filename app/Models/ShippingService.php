<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingService extends Model
{

    protected $table = "shipping_services";

    protected $fillable = [
        'name'
    ];
}
