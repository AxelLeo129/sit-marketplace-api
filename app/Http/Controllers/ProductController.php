<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Medicamentos del catálogo.
 */
#[Group('Catálogo', weight: 4)]
class ProductController extends Controller
{
    /**
     * Lista paginada con filtros opcionales:
     * `search` (nombre), `category_id`, `lab_id`, `per_page` (máx. 100)
     * e `include_inactive` (solo administrador).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'lab_id' => ['nullable', 'integer'],
            'include_inactive' => ['boolean'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ]);

        $includeInactive = ($filters['include_inactive'] ?? false) && Gate::allows('manage-catalog');

        $products = Product::query()
            ->with(['presentation', 'lab', 'categories'])
            ->unless($includeInactive, fn ($query) => $query->where('is_active', true))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->whereRelation('categories', 'categories.id', $id))
            ->when($filters['lab_id'] ?? null, fn ($query, $id) => $query->where('lab_id', $id))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->except('category_ids'));
            $product->categories()->sync($request->validated('category_ids', []));

            return $product;
        });

        return (new ProductResource($this->withRelations($product)))->response()->setStatusCode(201);
    }

    /**
     * Muestra un producto. Los inactivos solo los ve el administrador.
     */
    public function show(Product $product): ProductResource
    {
        abort_unless($product->is_active || Gate::allows('manage-catalog'), 404);

        return new ProductResource($this->withRelations($product));
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($request->safe()->except('category_ids'));
            $product->categories()->sync($request->validated('category_ids', []));
        });

        return new ProductResource($this->withRelations($product));
    }

    /**
     * Eliminación lógica: el producto sigue existiendo para recetas y pedidos anteriores.
     */
    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    private function withRelations(Product $product): Product
    {
        return $product->load(['presentation', 'lab', 'categories']);
    }
}
