<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda y elimina las imágenes de la aplicación en el disco configurado
 * en `filesystems.media_disk`.
 *
 * En la base de datos solo se guarda la ruta relativa (p. ej. "products/abc.webp"),
 * así cambiar de disco local a Firebase Storage no requiere migrar datos.
 */
class ImageStorage
{
    /**
     * Guarda la imagen con un nombre aleatorio dentro de la carpeta indicada
     * y devuelve su ruta. Si se pasa la ruta anterior, la elimina.
     */
    public function replace(UploadedFile $image, string $folder, ?string $previousPath = null): string
    {
        // hashName() genera un nombre aleatorio: nunca se usa el nombre que envía el cliente.
        $path = $this->disk()->putFile($folder, $image);

        $this->delete($previousPath);

        return $path;
    }

    /**
     * Elimina la imagen si existe.
     */
    public function delete(?string $path): void
    {
        if ($path) {
            $this->disk()->delete($path);
        }
    }

    /**
     * URL pública de la imagen, o null si no tiene.
     */
    public function url(?string $path): ?string
    {
        return $path ? $this->disk()->url($path) : null;
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.media_disk'));
    }
}
