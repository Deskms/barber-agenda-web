<?php

namespace App\Policies;

use App\Models\Cita;
use App\Models\User;

/**
 * La dueña ve y modifica cualquier cita; una clienta, solo las suyas.
 */
class CitaPolicy
{
    public function view(User $usuario, Cita $cita): bool
    {
        return $this->esDuenaOPropia($usuario, $cita);
    }

    public function update(User $usuario, Cita $cita): bool
    {
        return $this->esDuenaOPropia($usuario, $cita);
    }

    public function cancel(User $usuario, Cita $cita): bool
    {
        return $this->esDuenaOPropia($usuario, $cita);
    }

    private function esDuenaOPropia(User $usuario, Cita $cita): bool
    {
        if ($usuario->esAdmin()) {
            return true;
        }

        $cita->loadMissing('cliente');

        return $cita->cliente !== null && $cita->cliente->user_id === $usuario->id;
    }
}
