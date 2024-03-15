<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\GetOrderRequest;
use App\Http\Resources\Order\OrderResource;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\OrderRepository;

class OrderController extends Controller
{
    public function __construct(private OrderRepositoryInterface $order)
    {
    }


    public function index(GetOrderRequest $request)
    {
        $orders = $this->order->getAllOrders(["lineItems"], $request->validated());
        return OrderResource::collection($orders);
    }

    public function syncOrders()
    {
        $current_date = now();
        $start_date = $current_date->copy()->subMonth(3)->setTime(0, 0, 0);
        // return $start_date;
        $orders = $this->order->getOrdersFromApi($start_date, $current_date);
        $this->order->syncOrdersFromApi($orders);
        return $orders;
    }
}
