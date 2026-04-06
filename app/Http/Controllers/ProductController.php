<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::with(['images', 'categories', 'store'])
            ->where('is_active', true);

        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->has('category_id')) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $request->category_id));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return ProductResource::collection($query->paginate(15));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $store = \App\Models\Store::findOrFail($validated['store_id']);
        if ($store->user_id !== $request->user()->id) {
            abort(403, 'You do not own this store.');
        }

        $product = DB::transaction(function () use ($validated) {
            $product = Product::create([
                'store_id' => $validated['store_id'],
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'stock' => $validated['stock'],
            ]);

            if (! empty($validated['category_ids'])) {
                $product->categories()->attach($validated['category_ids']);
            }

            if (! empty($validated['image_urls'])) {
                foreach ($validated['image_urls'] as $index => $url) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'url' => $url,
                        'sort_order' => $index,
                    ]);
                }
            }

            return $product;
        });

        return (new ProductResource($product->load(['images', 'categories', 'store'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['images', 'categories', 'store']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        if ($product->store->user_id !== $request->user()->id) {
            abort(403, 'You do not own this product.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($product, $validated) {
            $data = collect($validated)->except(['category_ids', 'image_urls'])->toArray();

            if (isset($data['name'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $product->update($data);

            if (array_key_exists('category_ids', $validated)) {
                $product->categories()->sync($validated['category_ids'] ?? []);
            }

            if (array_key_exists('image_urls', $validated)) {
                $product->images()->delete();
                foreach (($validated['image_urls'] ?? []) as $index => $url) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'url' => $url,
                        'sort_order' => $index,
                    ]);
                }
            }
        });

        return new ProductResource($product->load(['images', 'categories', 'store']));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        if ($product->store->user_id !== $request->user()->id) {
            abort(403, 'You do not own this product.');
        }

        $product->delete();

        return response()->json(null, 204);
    }
}
