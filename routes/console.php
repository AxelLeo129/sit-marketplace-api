<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Borra a diario los tokens de Sanctum vencidos hace más de 24 horas.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Comprueba que el disco de imágenes funciona: sube un archivo de prueba, lo lee y lo borra.
Artisan::command('media:check', function () {
    $diskName = config('filesystems.media_disk');
    $disk = Storage::disk($diskName);
    $path = 'checks/'.Str::uuid().'.txt';

    $this->info("Disco de imágenes: {$diskName}");

    try {
        $disk->put($path, 'SIT Marketplace');
        $this->line('✓ Subida');
        $this->line($disk->get($path) === 'SIT Marketplace' ? '✓ Lectura' : '✗ Lectura: el contenido no coincide');
        $disk->delete($path);
        $this->line('✓ Borrado');
        $this->line('Ejemplo de URL pública: '.$disk->url('products/ejemplo.webp'));
    } catch (Throwable $e) {
        $this->error('Falló: '.$e->getMessage());

        return 1;
    }

    return 0;
})->purpose('Comprueba que el disco de imágenes (MEDIA_DISK) puede subir, leer y borrar archivos');
