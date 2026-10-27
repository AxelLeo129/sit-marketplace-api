<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sin pantalla de login web: un invitado en la API recibe 401, no una redirección.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');

        // Encabezados de seguridad en todas las respuestas de la API. Va en la pila global
        // para envolver también los errores (401, 403, 404, 429) que cortan antes la petición.
        $middleware->append(SecurityHeaders::class);

        // Detrás de un proxy o balanceador (Railway, Cloudflare...), solo se cree en el
        // X-Forwarded-For que llegue de estas IPs. Sin configurar no se confía en ninguno,
        // así nadie puede falsear su IP para saltarse los límites de intentos.
        $proxies = env('TRUSTED_PROXIES');
        if ($proxies) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La API siempre responde errores en JSON, aunque el cliente no envíe "Accept: application/json".
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
    })->create();
