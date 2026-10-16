<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Subida de una imagen (producto, avatar o logo de clínica).
 */
class ImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El permiso lo valida la ruta.
    }

    public function rules(): array
    {
        return [
            // Se valida el contenido real del archivo, no solo la extensión. Máximo 2 MB.
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
