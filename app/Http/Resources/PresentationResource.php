<?php

namespace App\Http\Resources;

use App\Models\Presentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Presentation
 */
class PresentationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit' => $this->unit,
            'quantity' => $this->quantity,
            // Texto listo para mostrar, p. ej. "Caja de 30 tabletas".
            'label' => "{$this->name} de {$this->quantity} {$this->unit}",
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
