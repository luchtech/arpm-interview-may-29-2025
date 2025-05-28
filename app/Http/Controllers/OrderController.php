<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\CartItem;

class OrderController extends Controller
{
    public function index()
    {
        $orderData = [];

        foreach (Order::query()->with(['customer', 'items'])->lazy() as $order) {
            if ($order instanceof Order) {
                $orderData[] = [
                    'order_id' => $order->id,
                    'customer_name' => $order->customer->name,
                    'total_amount' => $order->items->map(fn(CartItem $item) => $item->price * $item->quantity)->sum(),
                    'items_count' => $order->items->count(),
                    'last_added_to_cart' => $order->items()->latest()->first()->created_at,
                    'completed_order_exists' => $order->status == 'completed',
                    'created_at' => $order->created_at,
                ];
            }
        }

        return view('orders.index', ['orders' => $orderData]);
    }
}

