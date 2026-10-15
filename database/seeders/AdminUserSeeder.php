<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea el primer administrador con las credenciales de ADMIN_EMAIL y
 * ADMIN_PASSWORD del .env. Nunca se dejan credenciales en el código.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('marketplace.admin.email');
        $password = config('marketplace.admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD no definidos: no se creó el administrador.');

            return;
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => config('marketplace.admin.name'),
                'password' => $password,
                'role' => Role::Admin,
                'email_verified_at' => now(),
            ],
        );
    }
}
