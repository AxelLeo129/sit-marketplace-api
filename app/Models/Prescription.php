<?php

namespace App\Models;

use App\Enums\PrescriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Receta emitida por un médico. El call-center la convierte en pedido.
 */
#[Fillable(['doctor_id', 'patient_name', 'patient_phone', 'patient_email', 'symptoms', 'diagnosis', 'observations', 'issued_on', 'next_appointment_on', 'allows_call_center', 'status', 'callback_at'])]
class Prescription extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'next_appointment_on' => 'date',
            'allows_call_center' => 'boolean',
            'status' => PrescriptionStatus::class,
            'callback_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }
}
