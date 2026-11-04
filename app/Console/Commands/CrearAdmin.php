<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea (o promueve) la cuenta de la dueña sin dejar contraseñas en el código.
 *   php artisan usuarios:crear-admin dueña@ejemplo.com --nombre="Joss"
 */
class CrearAdmin extends Command
{
    protected $signature = 'usuarios:crear-admin {email} {--nombre=Dueña} {--password=}';

    protected $description = 'Crea o promueve una cuenta con rol de administradora (dueña).';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Contraseña (mínimo 8 caracteres)');

        $validador = Validator::make(
            ['email' => $this->argument('email'), 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8']],
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario = User::updateOrCreate(
            ['email' => $this->argument('email')],
            ['name' => $this->option('nombre'), 'password' => $password, 'rol' => User::ROL_ADMIN, 'activo' => true],
        );

        $this->info("Administradora lista: {$usuario->email} (id {$usuario->id}).");

        return self::SUCCESS;
    }
}
