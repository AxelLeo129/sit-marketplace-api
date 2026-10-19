<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ImageStorage;
use Dedoc\Scramble\Attributes\Group;

/**
 * Imagen de un producto.
 */
#[Group('Catálogo', weight: 4)]
class ProductImageController extends Controller
{
    public function __construct(private readonly ImageStorage $images) {}

    /**
     * Sube o reemplaza la imagen; la anterior se elimina.
     */
    public function store(ImageRequest $request, Product $product): ProductResource
    {
        $product->update([
            'image_path' => $this->images->replace($request->file('image'), 'products', $product->image_path),
        ]);

        return new ProductResource($product->load(['presentation', 'lab', 'categories']));
    }

    public function destroy(Product $product): ProductResource
    {
        $this->images->delete($product->image_path);
        $product->update(['image_path' => null]);

        return new ProductResource($product->load(['presentation', 'lab', 'categories']));
    }
}
