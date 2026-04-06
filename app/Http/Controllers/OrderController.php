<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.product.images', 'address'])
            ->latest()
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $address = $request->user()->addresses()->findOrFail($validated['address_id']);

        $order = DB::transaction(function () use ($request, $validated, $address) {
            $total = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::where('is_active', true)->findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    abort(422, "Insufficient stock for product: {$product->name}");
                }

                $subtotal = $product->price * $item['quantity'];
                $total += $subtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                ];

                $product->decrement('stock', $item['quantity']);
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'address_id' => $address->id,
                'total' => $total,
                'notes' => $validated['notes'] ?? null,
            ]);

            $order->items()->createMany($itemsData);

            return $order;
        });

        return (new OrderResource($order->load(['items.product.images', 'address'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        return new OrderResource($order->load(['items.product.images', 'address']));
    }

    public function updateStatus(Request $request, Order $order): OrderResource
    {
        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,shipped,delivered,cancelled'],
        ]);

        $now = now();

        match ($validated['status']) {
            'confirmed' => $order->confirmed_at = $now,
            'shipped' => $order->shipped_at = $now,
            'delivered' => $order->delivered_at = $now,
            'cancelled' => $this->restoreStock($order),
        };

        $order->status = $validated['status'];
        $order->save();

        return new OrderResource($order->load(['items.product', 'address']));
    }

    private function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }
    }
}
