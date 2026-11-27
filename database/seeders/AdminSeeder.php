<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea (o actualiza) el administrador inicial para producción.
 * Uso: ADMIN_EMAIL, ADMIN_PASSWORD y ADMIN_NAME en el .env y luego
 *      php artisan db:seed --class=AdminSeeder --force
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            throw new \RuntimeException('Define ADMIN_EMAIL y ADMIN_PASSWORD antes de crear el administrador inicial.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => $password,
                'rol' => 'ADMIN',
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info("Administrador listo: {$email}");
    }
}
