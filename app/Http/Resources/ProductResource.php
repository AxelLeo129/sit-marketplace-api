<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            // El costo revela el margen: solo lo ve quien administra el catálogo.
            'cost' => $this->when(Gate::allows('manage-catalog'), $this->cost),
            'stock' => $this->stock,
            'warehouse' => $this->warehouse,
            'is_active' => $this->is_active,
            'is_regulated' => $this->is_regulated,
            'observations' => $this->observations,
            'image_url' => app(ImageStorage::class)->url($this->image_path),
            'presentation' => new PresentationResource($this->whenLoaded('presentation')),
            'lab' => new LabResource($this->whenLoaded('lab')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'updated_at' => $this->updated_at,
        ];
    }
}
