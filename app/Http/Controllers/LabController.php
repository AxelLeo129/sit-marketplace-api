<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabRequest;
use App\Http\Resources\LabResource;
use App\Models\Lab;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Laboratorios fabricantes.
 */
#[Group('Catálogo', weight: 4)]
class LabController extends Controller
{
    /**
     * Lista todos los laboratorios ordenados por nombre.
     */
    public function index(): AnonymousResourceCollection
    {
        return LabResource::collection(Lab::withCount('products')->orderBy('name')->get());
    }

    public function store(LabRequest $request): JsonResponse
    {
        $lab = Lab::create($request->validated());

        return (new LabResource($lab))->response()->setStatusCode(201);
    }

    public function show(Lab $lab): LabResource
    {
        return new LabResource($lab->loadCount('products'));
    }

    public function update(LabRequest $request, Lab $lab): LabResource
    {
        $lab->update($request->validated());

        return new LabResource($lab);
    }

    /**
     * Elimina el laboratorio si ningún producto (ni eliminado) lo usa.
     */
    public function destroy(Lab $lab): Response|JsonResponse
    {
        if ($lab->products()->withTrashed()->exists()) {
            return response()->json(['message' => 'El laboratorio tiene productos asociados.'], 409);
        }

        $lab->delete();

        return response()->noContent();
    }
}
