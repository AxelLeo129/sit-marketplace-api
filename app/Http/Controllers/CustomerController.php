<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Clientes que compran los medicamentos. Los gestiona el call-center.
 */
#[Group('Clientes', weight: 6)]
class CustomerController extends Controller
{
    /**
     * Busca clientes por teléfono, nombre o NIT (`search`). El call-center
     * suele buscar por el teléfono que aparece en la receta.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ]);

        $customers = Customer::query()
            ->with('addresses')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('phone', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('tax_id', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);

        return CustomerResource::collection($customers);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        return (new CustomerResource($customer->load('addresses')))->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer->load('addresses'));
    }

    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($customer->load('addresses'));
    }
}
