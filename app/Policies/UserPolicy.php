<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Quién puede administrar usuarios.
 *
 * - El administrador gestiona a todos.
 * - El visitador solo ve, crea y edita a los médicos que tiene asignados.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(Role::Admin, Role::SalesRep);
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->hasRole(Role::Admin) || $this->isOwnDoctor($actor, $user);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::Admin, Role::SalesRep);
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasRole(Role::Admin) || $this->isOwnDoctor($actor, $user);
    }

    /**
     * Solo el administrador elimina usuarios, y nunca a sí mismo.
     */
    public function delete(User $actor, User $user): bool
    {
        return $actor->hasRole(Role::Admin) && ! $actor->is($user);
    }

    /**
     * Indica si el usuario es un médico asignado al visitador.
     */
    private function isOwnDoctor(User $actor, User $user): bool
    {
        return $actor->hasRole(Role::SalesRep)
            && $user->hasRole(Role::Doctor)
            && $user->sales_rep_id === $actor->id;
    }
}
