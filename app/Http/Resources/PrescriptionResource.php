<?php

namespace App\Http\Resources;

use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Prescription
 */
class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'patient_name' => $this->patient_name,
            'patient_phone' => $this->patient_phone,
            'patient_email' => $this->patient_email,
            // Datos de salud: solo para el médico que emitió la receta (ver PrescriptionPolicy).
            $this->mergeWhen($user->can('viewClinicalData', $this->resource), fn () => [
                'symptoms' => $this->symptoms,
                'diagnosis' => $this->diagnosis,
                'observations' => $this->observations,
            ]),
            'issued_on' => $this->issued_on->toDateString(),
            'next_appointment_on' => $this->next_appointment_on?->toDateString(),
            'allows_call_center' => $this->allows_call_center,
            'callback_at' => $this->callback_at,
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
                'collegiate_number' => $this->doctor->collegiate_number,
                'doctor_profile' => $this->doctor->doctorProfile
                    ? new DoctorProfileResource($this->doctor->doctorProfile)
                    : null,
            ]),
            'items' => PrescriptionItemResource::collection($this->whenLoaded('items')),
            'can' => [
                'update' => $user->can('update', $this->resource),
                'handle_call' => $user->can('handleCall', $this->resource),
                'send' => $user->can('send', $this->resource),
            ],
            'created_at' => $this->created_at,
        ];
    }
}
