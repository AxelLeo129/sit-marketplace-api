<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encabezados de seguridad recomendados por OWASP para todas las respuestas de la API.
 *
 * La API solo devuelve JSON, así que su política de contenido (CSP) no permite
 * cargar nada: si alguien lograra que el navegador interpretara una respuesta
 * como HTML, no podría ejecutar scripts ni embeberla en otra página.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Solo la API: la documentación (/docs/api) es HTML con sus propios scripts.
        if (! $request->is('api/*')) {
            return $response;
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
            'Cross-Origin-Resource-Policy' => 'same-site',
        ];

        // HSTS solo tiene sentido por HTTPS (en local con Laragon es HTTP).
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        $response->headers->add($headers);
        // No revelar la versión de PHP.
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        return $response;
    }
}
