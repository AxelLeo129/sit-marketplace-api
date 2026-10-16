<?php

namespace App\Http\Resources;

use App\Models\PrescriptionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PrescriptionItem
 */
class PrescriptionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dosage_instructions' => $this->dosage_instructions,
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
