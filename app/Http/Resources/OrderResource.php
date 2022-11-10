<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'vendor_id' => $this->vendor_id,
            'vendor_order_no' => $this->vendor_order_no,
            'address' => $this->address ?? "",
            'arriving_date' => $this->arriving_date ?? "",
            'ordered_date' => $this->ordered_date ?? "",
            'items_total_price' => $this->items_total_price ?? "",
            'shipping_price' => $this->shipping_price ?? "",
            'payment_status' => "Paid",
            'tax' => $this->tax ?? "",
            'total_price' => $this->total_price ?? "",
            'shipping_service_id' => $this->shipping_service_id ?? "",
            'shipping_tracking_id' => $this->shipping_tracking_id ?? "",
            'is_archived' => $this->is_archived,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'shippingDetails' => ShippingDetailResource::collection($this->shippingDetails),
            'items' => ItemResource::collection($this->items),
            'vendor' => new VendorResource($this->vendor),
            'shippingService' => $this->shippingService ?? "",
        ];
    }
}
