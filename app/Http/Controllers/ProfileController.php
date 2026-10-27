<?php

namespace App\Http\Controllers;

use App\Enums\AuthEventType;
use App\Enums\Role;
use App\Http\Requests\ImageRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthAuditLog;
use App\Services\ImageStorage;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * El usuario autenticado consulta y edita sus propios datos.
 */
#[Group('Mi perfil', weight: 2)]
class ProfileController extends Controller
{
    private const RELATIONS = ['salesRep', 'doctorProfile', 'specialties'];

    public function __construct(private readonly ImageStorage $images) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load(self::RELATIONS));
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $user->update($request->safe()->except(['doctor_profile', 'category_ids']));

            if ($user->hasRole(Role::Doctor)) {
                $user->saveDoctorData($request->validated('doctor_profile'), $request->validated('category_ids'));
            }
        });

        return new UserResource($user->load(self::RELATIONS));
    }

    /**
     * Cambia la contraseña y cierra las demás sesiones (se conserva la actual).
     */
    public function updatePassword(UpdatePasswordRequest $request, AuthAuditLog $audit): JsonResponse
    {
        $user = $request->user();

        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();
        $audit->record(AuthEventType::PasswordChanged, $user);

        return response()->json(['message' => 'Contraseña actualizada.']);
    }

    public function updateAvatar(ImageRequest $request): UserResource
    {
        $user = $request->user();

        $user->update([
            'avatar_path' => $this->images->replace($request->file('image'), 'avatars', $user->avatar_path),
        ]);

        return new UserResource($user->load(self::RELATIONS));
    }

    /**
     * Logo de la clínica que se imprime en las recetas (solo médicos).
     */
    public function updateClinicLogo(ImageRequest $request): UserResource
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::Doctor), 403);

        $profile = $user->doctorProfile()->firstOrCreate();
        $profile->update([
            'clinic_logo_path' => $this->images->replace($request->file('image'), 'clinic-logos', $profile->clinic_logo_path),
        ]);

        return new UserResource($user->load(self::RELATIONS));
    }
}
