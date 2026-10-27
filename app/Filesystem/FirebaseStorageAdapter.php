<?php

namespace App\Filesystem;

use Illuminate\Filesystem\FilesystemAdapter;

/**
 * Disco de Laravel sobre el bucket de Cloud Storage for Firebase.
 *
 * Sube y borra con la cuenta de servicio de la API. Las URLs usan el formato de
 * descarga de Firebase, que respeta las reglas de seguridad de Storage
 * (ver storage.rules en el repositorio del frontend): las imágenes se pueden ver
 * sin que el bucket sea público.
 */
class FirebaseStorageAdapter extends FilesystemAdapter
{
    /**
     * URL de descarga, p. ej.
     * https://firebasestorage.googleapis.com/v0/b/mi-proyecto.firebasestorage.app/o/products%2Fabc.webp?alt=media
     *
     * @param  string  $path
     */
    public function url($path): string
    {
        return sprintf(
            'https://firebasestorage.googleapis.com/v0/b/%s/o/%s?alt=media',
            $this->config['bucket'],
            // Firebase espera la ruta completa codificada, incluidas las "/".
            rawurlencode(ltrim($path, '/')),
        );
    }
}
