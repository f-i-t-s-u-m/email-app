<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ShippingDetailResource;
use App\Http\Resources\ItemResource;
use Illuminate\Support\Facades\Validator;
use App\Models\Order;
use App\Models\Item;
use App\Models\ShippingDetail;

class OrdersController extends BaseController
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $orders = Order::query()->where('user_id', $userId)
            ->with('shippingDetails', 'vendor', 'shippingService')
            ->filter($request->query());

        $archivedOrders =  (clone $orders)->where('is_archived', true)->get();
        $nonArchivedOrders = (clone $orders)->where('is_archived', false)->get();
        if (count($orders->get()) > 0) {
            return $this->sendResponse(
                "User orders",
                [
                    "orders" => OrderResource::collection($nonArchivedOrders),
                    "archived" => OrderResource::collection($archivedOrders),
                    'filterData' => Order::filterData(),
                ]
            );
        } else {
            return $this->sendResponse("No record found");
        }
    }

    public function trackingDetails(Request $request)
    {
        $userId = $request->user()->id;
        $orderId = $request->order_id;
        $validator = $this->trackingDetailsValidator($request->all());

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors());
        }

        $order = Order::where('id', $orderId)->where('user_id', $userId)->first();
        if ($order) {
            $data = [
                "shippingDetails" => ShippingDetailResource::collection($order->shippingDetails),
                "items" => ItemResource::collection($order->items)
            ];
            return $this->sendResponse("Tracking details", $data);
        } else {
            return $this->sendError("No record found");
        }
    }

    protected function trackingDetailsValidator(array $data)
    {
        return Validator::make($data, [
            'order_id'      => ['required', 'integer'],
        ]);
    }

    public function addToArchive($id): JsonResponse
    {
        $order = Order::query()->findOrFail($id);

        if ($order->is_archived) {
            return $this->sendError("This order already archived!");
        }

        $order->update([
            'is_archived' => True
        ]);

        return $this->sendResponse("Successfully Archived!", $order);
    }

    public function removeFromArchive($id): JsonResponse
    {
        $order = Order::query()->findOrFail($id);

        if (!$order->is_archived) {
            return $this->sendError("This order already is not archived!");
        }

        $order->update([
            'is_archived' => False
        ]);

        return $this->sendResponse("Order successfully unarchived!", $order);
    }
}
