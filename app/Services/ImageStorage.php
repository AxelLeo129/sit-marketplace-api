<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guarda y elimina las imágenes de la aplicación en el disco configurado
 * en `filesystems.media_disk`.
 *
 * Cada imagen se vuelve a dibujar y se guarda como WebP: así se descartan los
 * metadatos (EXIF, ubicación GPS de las fotos del celular) y cualquier contenido
 * escondido dentro del archivo original. También se reduce si es muy grande.
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
        // Nombre aleatorio: nunca se usa el nombre que envía el cliente.
        $path = $folder.'/'.Str::random(40).'.webp';
        $this->disk()->put($path, $this->reencode($image));

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

    /**
     * Vuelve a dibujar la imagen en un lienzo nuevo (sin metadatos) y la devuelve en WebP.
     *
     * @throws ValidationException Si el archivo no se puede leer como imagen.
     */
    private function reencode(UploadedFile $image): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($image->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages(['image' => 'El archivo no es una imagen válida.']);
        }

        // Reduce proporcionalmente si algún lado supera el máximo.
        $max = config('security.images.max_dimension');
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $max / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        // Conserva la transparencia de PNG y WebP.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagewebp($canvas, null, config('security.images.quality'));

        return (string) ob_get_clean();
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.media_disk'));
    }
}
