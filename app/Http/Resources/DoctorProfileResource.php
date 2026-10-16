<?php

namespace App\Http\Resources;

use App\Models\DoctorProfile;
use App\Services\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DoctorProfile
 */
class DoctorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'clinic_name' => $this->clinic_name,
            'clinic_phone' => $this->clinic_phone,
            'clinic_address' => $this->clinic_address,
            'clinic_logo_url' => app(ImageStorage::class)->url($this->clinic_logo_path),
            'keeps_clinical_record' => $this->keeps_clinical_record,
            'show_alerts' => $this->show_alerts,
        ];
    }
}
