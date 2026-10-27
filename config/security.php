<?php

/*
| Ajustes de seguridad de la API. Los valores por defecto son los recomendados;
| se pueden cambiar por entorno sin tocar código.
*/
return [

    // Bloqueo de cuenta tras contraseñas incorrectas seguidas.
    'lockout' => [
        // Intentos fallidos antes de bloquear.
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        // Duración del primer bloqueo; cada bloqueo siguiente sin un login correcto la duplica.
        'base_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
        // Tope del bloqueo (24 h).
        'max_minutes' => (int) env('LOGIN_LOCKOUT_MAX_MINUTES', 1440),
    ],

    // Rechaza contraseñas que aparecen en filtraciones públicas (Have I Been Pwned).
    // Solo se envían los primeros 5 caracteres del hash SHA-1, nunca la contraseña.
    'check_breached_passwords' => (bool) env('CHECK_BREACHED_PASSWORDS', true),

    // Días que se guardan los eventos de autenticación antes de borrarse.
    'auth_events_retention_days' => (int) env('AUTH_EVENTS_RETENTION_DAYS', 365),

    // Imágenes subidas: se recodifican a WebP (sin metadatos) con este lado máximo.
    'images' => [
        'max_dimension' => 1600,
        'quality' => 85,
    ],
];
