{{-- Receta enviada al paciente. No incluye síntomas ni diagnóstico. --}}
<x-mail::message>
# Receta No. {{ $prescription->id }}

Hola {{ $prescription->patient_name }}, esta es la receta que te emitió
**{{ $prescription->doctor->name }}** el {{ $prescription->issued_on->format('d/m/Y') }}.

<x-mail::table>
| Medicamento | Indicaciones |
|:--|:--|
@foreach ($prescription->items as $item)
| {{ $item->product->name }} ({{ $item->product->presentation->name }} de {{ $item->product->presentation->quantity }} {{ $item->product->presentation->unit }}) | {{ $item->dosage_instructions }} |
@endforeach
</x-mail::table>

@if ($prescription->next_appointment_on)
**Próxima cita:** {{ $prescription->next_appointment_on->format('d/m/Y') }}
@endif

@if ($profile = $prescription->doctor->doctorProfile)
{{ $profile->clinic_name }}<br>
{{ $profile->clinic_address }}<br>
{{ $profile->clinic_phone }}
@endif

{{ config('app.name') }}
</x-mail::message>
