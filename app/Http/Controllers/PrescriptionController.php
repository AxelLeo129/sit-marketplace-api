<?php

namespace App\Http\Controllers;

use App\Enums\PrescriptionStatus;
use App\Http\Requests\PrescriptionRequest;
use App\Http\Requests\ReschedulePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Mail\PrescriptionMail;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Recetas: el médico las emite y el call-center las gestiona.
 * Los permisos están en App\Policies\PrescriptionPolicy.
 */
class PrescriptionController extends Controller
{
    private const RELATIONS = ['doctor.doctorProfile', 'items.product.presentation'];

    /**
     * Lista paginada de las recetas que el usuario puede ver.
     *
     * Filtros: `status`, `search` (nombre o teléfono del paciente),
     * `issued_from`, `issued_to` y `per_page`. Las reagendadas se ordenan
     * por la hora de la próxima llamada; el resto, de la más reciente a la más antigua.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Prescription::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PrescriptionStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'issued_from' => ['nullable', 'date'],
            'issued_to' => ['nullable', 'date', 'after_or_equal:issued_from'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ]);

        $status = PrescriptionStatus::tryFrom($filters['status'] ?? '');

        $prescriptions = Prescription::query()
            ->visibleTo($request->user())
            ->with(self::RELATIONS)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('patient_name', 'like', "%{$search}%")
                ->orWhere('patient_phone', 'like', "%{$search}%")))
            ->when($filters['issued_from'] ?? null, fn ($query, $date) => $query->whereDate('issued_on', '>=', $date))
            ->when($filters['issued_to'] ?? null, fn ($query, $date) => $query->whereDate('issued_on', '<=', $date))
            ->when(
                $status === PrescriptionStatus::Rescheduled,
                fn ($query) => $query->orderBy('callback_at'),
                fn ($query) => $query->latest('issued_on')->latest('id'),
            )
            ->paginate($filters['per_page'] ?? 20);

        return PrescriptionResource::collection($prescriptions);
    }

    /**
     * El médico emite una receta. La fecha de emisión la pone el servidor.
     */
    public function store(PrescriptionRequest $request): JsonResponse
    {
        $prescription = DB::transaction(function () use ($request) {
            $prescription = $request->user()->prescriptions()->create([
                ...$request->safe()->except('items'),
                'issued_on' => today(),
                'status' => PrescriptionStatus::Pending,
            ]);
            $prescription->items()->createMany($request->validated('items'));

            return $prescription;
        });

        return (new PrescriptionResource($prescription->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Prescription $prescription): PrescriptionResource
    {
        Gate::authorize('view', $prescription);

        return new PrescriptionResource($prescription->load(self::RELATIONS));
    }

    /**
     * El médico corrige su receta mientras el call-center no la haya procesado.
     */
    public function update(PrescriptionRequest $request, Prescription $prescription): PrescriptionResource
    {
        DB::transaction(function () use ($request, $prescription) {
            $prescription->update($request->safe()->except('items'));
            $prescription->items()->delete();
            $prescription->items()->createMany($request->validated('items'));
        });

        return new PrescriptionResource($prescription->load(self::RELATIONS));
    }

    /**
     * El call-center agenda una nueva llamada con el paciente.
     */
    public function reschedule(ReschedulePrescriptionRequest $request, Prescription $prescription): PrescriptionResource
    {
        $prescription->update([
            'status' => PrescriptionStatus::Rescheduled,
            'callback_at' => $request->validated('callback_at'),
        ]);

        return new PrescriptionResource($prescription->load(self::RELATIONS));
    }

    /**
     * El paciente no quiere el pedido.
     */
    public function cancel(Prescription $prescription): PrescriptionResource
    {
        Gate::authorize('handleCall', $prescription);

        $prescription->update(['status' => PrescriptionStatus::Cancelled, 'callback_at' => null]);

        return new PrescriptionResource($prescription->load(self::RELATIONS));
    }

    /**
     * El médico envía la receta al correo del paciente. Si manda un correo
     * distinto, se guarda como el correo del paciente.
     */
    public function send(Request $request, Prescription $prescription): JsonResponse
    {
        Gate::authorize('send', $prescription);

        $email = $request->validate(['email' => ['nullable', 'email', 'max:255']])['email'] ?? $prescription->patient_email;

        if (! $email) {
            return response()->json(['message' => 'La receta no tiene correo del paciente.'], 422);
        }

        $prescription->update(['patient_email' => $email]);
        Mail::to($email, $prescription->patient_name)->send(new PrescriptionMail($prescription));

        return response()->json(['message' => "Receta enviada a {$email}."]);
    }
}
