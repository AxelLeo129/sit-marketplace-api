<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Prescription;
use App\Models\User;

/**
 * Quién puede ver y gestionar recetas.
 *
 * - El médico emite y edita sus recetas mientras sigan abiertas.
 * - El call-center ve las que el paciente autorizó y las reagenda o cancela.
 * - El administrador ve todas.
 */
class PrescriptionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(Role::Admin, Role::Doctor, Role::CallCenter);
    }

    public function view(User $actor, Prescription $prescription): bool
    {
        return match ($actor->role) {
            Role::Admin => true,
            Role::Doctor => $this->isAuthor($actor, $prescription),
            Role::CallCenter => $prescription->allows_call_center,
            default => false,
        };
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::Doctor);
    }

    public function update(User $actor, Prescription $prescription): bool
    {
        return $this->isAuthor($actor, $prescription) && $prescription->status->isOpen();
    }

    /**
     * Reagendar la llamada o cancelar la receta.
     */
    public function handleCall(User $actor, Prescription $prescription): bool
    {
        return $actor->hasRole(Role::CallCenter, Role::Admin)
            && $prescription->allows_call_center
            && $prescription->status->isOpen();
    }

    /**
     * Enviar la receta por correo al paciente.
     */
    public function send(User $actor, Prescription $prescription): bool
    {
        return $this->isAuthor($actor, $prescription);
    }

    /**
     * Síntomas, diagnóstico y observaciones son datos de salud:
     * solo los ve el médico que emitió la receta.
     */
    public function viewClinicalData(User $actor, Prescription $prescription): bool
    {
        return $this->isAuthor($actor, $prescription);
    }

    private function isAuthor(User $actor, Prescription $prescription): bool
    {
        return $actor->hasRole(Role::Doctor) && $prescription->doctor_id === $actor->id;
    }
}
