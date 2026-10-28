<?php

namespace Tests\Feature;

use Tests\TestCase;
use UnitEnum;

/**
 * Lo que necesita el despliegue en producción (Railway) para arrancar.
 */
class DeploymentTest extends TestCase
{
    public function test_configuration_can_be_cached(): void
    {
        // Al arrancar, el contenedor corre "php artisan optimize", que guarda la configuración
        // con var_export: un objeto en cualquier archivo de config/ rompe el despliegue
        // (los enums sí se pueden guardar).
        $objects = [];
        $config = config()->all();
        array_walk_recursive($config, function (mixed $value, string|int $key) use (&$objects): void {
            if (is_object($value) && ! $value instanceof UnitEnum) {
                $objects[] = $key;
            }
        });

        $this->assertSame([], $objects, 'Valores de configuración que no se pueden cachear.');
    }

    public function test_health_check_responds(): void
    {
        // Railway espera un 200 en /up antes de pasar el tráfico a la nueva versión.
        $this->get('/up')->assertOk();
    }
}
