<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuario del sistema. Su rol define qué módulos puede usar.
 */
#[Fillable(['name', 'email', 'collegiate_number', 'phone', 'birth_date', 'avatar_path', 'role', 'sales_rep_id', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Indica si el usuario tiene alguno de los roles dados.
     */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Guarda el perfil de clínica y las especialidades de un médico.
     * Un valor null deja ese dato como está.
     *
     * @param  array<string, mixed>|null  $profile
     * @param  array<int, int>|null  $specialtyIds
     */
    public function saveDoctorData(?array $profile, ?array $specialtyIds): void
    {
        if ($profile !== null) {
            $this->doctorProfile()->updateOrCreate([], $profile);
        }

        if ($specialtyIds !== null) {
            $this->specialties()->sync($specialtyIds);
        }
    }

    /**
     * Datos de clínica (solo médicos).
     */
    public function doctorProfile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class);
    }

    /**
     * Especialidades del médico.
     */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Visitador médico asignado a este médico.
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sales_rep_id');
    }

    /**
     * Médicos que atiende este visitador.
     */
    public function doctors(): HasMany
    {
        return $this->hasMany(self::class, 'sales_rep_id');
    }

    /**
     * Recetas emitidas por este médico.
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'doctor_id');
    }

    /**
     * Pedidos asignados a este mensajero.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    /**
     * Comisiones ganadas por el usuario.
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
