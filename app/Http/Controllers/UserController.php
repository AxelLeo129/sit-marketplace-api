<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Administración de usuarios (administrador) y de médicos (visitador).
 * Los permisos están en App\Policies\UserPolicy.
 */
#[Group('Usuarios', weight: 3)]
class UserController extends Controller
{
    private const RELATIONS = ['salesRep', 'doctorProfile', 'specialties'];

    /**
     * Lista paginada con filtros `role`, `search` (nombre, correo o colegiado),
     * `is_active` y `per_page`. El visitador solo ve a sus médicos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'role' => ['nullable', Rule::enum(Role::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ]);

        $actor = $request->user();

        $users = User::query()
            ->with(self::RELATIONS)
            ->when($actor->hasRole(Role::SalesRep), fn ($query) => $query
                ->where('role', Role::Doctor)
                ->where('sales_rep_id', $actor->id))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', $filters['is_active']))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('collegiate_number', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->except(['doctor_profile', 'category_ids']));

            if ($user->hasRole(Role::Doctor)) {
                $user->saveDoctorData($request->validated('doctor_profile', []), $request->validated('category_ids', []));
            }

            return $user;
        });

        return (new UserResource($user->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load(self::RELATIONS));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        DB::transaction(function () use ($request, $user) {
            $user->update($request->safe()->except(['doctor_profile', 'category_ids']));

            if ($user->hasRole(Role::Doctor)) {
                $user->saveDoctorData($request->validated('doctor_profile'), $request->validated('category_ids'));
            }

            // Si se desactivó o se le asignó otra contraseña, se cierran todas sus sesiones.
            if (! $user->is_active || $request->has('password')) {
                $user->tokens()->delete();
            }
        });

        return new UserResource($user->load(self::RELATIONS));
    }

    /**
     * Eliminación lógica: sus recetas y pedidos siguen existiendo.
     */
    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }
}
