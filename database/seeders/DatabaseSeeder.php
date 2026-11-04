<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogoSeeder::class);

        // La cuenta de la dueña solo se crea si se definió en el .env.
        if (env('ADMIN_EMAIL') && env('ADMIN_PASSWORD')) {
            User::updateOrCreate(
                ['email' => env('ADMIN_EMAIL')],
                ['name' => 'Dueña', 'password' => env('ADMIN_PASSWORD'), 'rol' => User::ROL_ADMIN, 'activo' => true],
            );
        }
    }
}
