<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'rol', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Dueña del negocio: administra la agenda completa. */
    public const ROL_ADMIN = 'admin';

    /** Clienta con cuenta en la web: solo ve y gestiona sus propias citas. */
    public const ROL_CLIENTE = 'cliente';

    public const ROLES = [self::ROL_ADMIN, self::ROL_CLIENTE];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class);
    }

    public function esAdmin(): bool
    {
        return $this->rol === self::ROL_ADMIN;
    }
}
