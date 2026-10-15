<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Carga el administrador inicial y, solo en local, datos de demostración.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
