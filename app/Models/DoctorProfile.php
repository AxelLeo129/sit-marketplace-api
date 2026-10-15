<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datos de la clínica de un médico; se imprimen en sus recetas.
 */
#[Fillable(['user_id', 'clinic_name', 'clinic_phone', 'clinic_address', 'clinic_logo_path', 'keeps_clinical_record', 'show_alerts'])]
class DoctorProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'keeps_clinical_record' => 'boolean',
            'show_alerts' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
