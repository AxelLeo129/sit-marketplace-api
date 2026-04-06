<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $stores = Store::withCount('products')
            ->where('is_active', true)
            ->paginate(15);

        return StoreResource::collection($stores);
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;
        $validated['slug'] = Str::slug($validated['name']);

        $store = Store::create($validated);

        return (new StoreResource($store))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Store $store): StoreResource
    {
        return new StoreResource($store->loadCount('products'));
    }

    public function update(UpdateStoreRequest $request, Store $store): StoreResource
    {
        $this->authorizeOwner($request, $store);

        $validated = $request->validated();

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $store->update($validated);

        return new StoreResource($store);
    }

    public function destroy(Request $request, Store $store): JsonResponse
    {
        $this->authorizeOwner($request, $store);

        $store->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwner(Request $request, Store $store): void
    {
        if ($store->user_id !== $request->user()->id) {
            abort(403, 'You do not own this store.');
        }
    }
}
