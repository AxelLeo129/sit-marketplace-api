<?php

namespace App\Http\Controllers;

use App\Http\Requests\PresentationRequest;
use App\Http\Resources\PresentationResource;
use App\Models\Presentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Presentaciones de los medicamentos.
 */
class PresentationController extends Controller
{
    /**
     * Lista todas las presentaciones ordenadas por nombre.
     */
    public function index(): AnonymousResourceCollection
    {
        return PresentationResource::collection(Presentation::withCount('products')->orderBy('name')->get());
    }

    public function store(PresentationRequest $request): JsonResponse
    {
        $presentation = Presentation::create($request->validated());

        return (new PresentationResource($presentation))->response()->setStatusCode(201);
    }

    public function show(Presentation $presentation): PresentationResource
    {
        return new PresentationResource($presentation->loadCount('products'));
    }

    public function update(PresentationRequest $request, Presentation $presentation): PresentationResource
    {
        $presentation->update($request->validated());

        return new PresentationResource($presentation);
    }

    /**
     * Elimina la presentación si ningún producto (ni eliminado) la usa.
     */
    public function destroy(Presentation $presentation): Response|JsonResponse
    {
        if ($presentation->products()->withTrashed()->exists()) {
            return response()->json(['message' => 'La presentación tiene productos asociados.'], 409);
        }

        $presentation->delete();

        return response()->noContent();
    }
}
