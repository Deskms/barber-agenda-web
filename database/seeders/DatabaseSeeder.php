<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Reejecutable: no duplica servicios, horarios, clientas ni la cuenta del panel.
     */
    public function run(): void
    {
        $this->call(CatalogoSeeder::class);

        // La contraseña de la dueña vive solo en el .env, nunca en el repositorio.
        $email    = env('PANEL_ADMIN_EMAIL');
        $password = env('PANEL_ADMIN_PASSWORD');

        if ($email && $password) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => env('PANEL_ADMIN_NOMBRE', 'Dueña'), 'password' => Hash::make($password)],
            );
        } else {
            $this->command?->warn('Sin PANEL_ADMIN_EMAIL / PANEL_ADMIN_PASSWORD en el .env: no se creó la cuenta del panel.');
        }
    }
}
