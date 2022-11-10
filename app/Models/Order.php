<?php

namespace App\Models;

use App\Http\Resources\VendorResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Order extends Model
{

    protected $table = "orders";
    protected $dates = ['ordered_date', 'arriving_date'];
    protected $fillable = [
        'user_id',
        'vendor_id',
        'vendor_order_no',
        'address',
        'arriving_date',
        'ordered_date',
        'items_total_price',
        'shipping_price',
        'tax',
        'total_price',
        'shipping_service_id',
        'shipping_tracking_id',
        'is_archived',
        'mail_id',
        'tracking_url',
        'delivery_status'

    ];


    public function shippingDetails()
    {
        return $this->hasMany(ShippingDetail::class, 'order_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function shippingService()
    {
        return $this->belongsTo(ShippingService::class, 'shipping_service_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'order_id');
    }

    public function scopeFilter($builder, $filter)
    {
        if(isset($filter['vendorName']))
        {
            $builder->whereHas('vendor', function($query) use($filter){
                $query->where('name', $filter['vendorName']);
            });
        }

        if(isset($filter['orderByDate'])) {
            $builder->orderBy('ordered_date', $filter['orderByDate']);
        } else {
            $builder->orderBy('id', 'desc');
        }

        if(isset($filter['status']))
        {
            $builder->whereHas('shippingDetails', function($query) use($filter){
                $query->where('status', $filter['status']);
            });
        }
    }

    public static function filterData(){
        $orders = Order::query()->where('user_id', Auth::id())->pluck('vendor_id')->unique()->toArray();
       
        return [
            'vendorNames' => Vendor::query()->whereIn('id', $orders)->pluck('name'),
            'statuses' => DeliveryStatus::all('status_en', 'status_de'),
        ];
    }
}
