<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Direcciones de facturación y entrega de un cliente.
 */
#[Group('Clientes', weight: 6)]
class CustomerAddressController extends Controller
{
    public function store(CustomerAddressRequest $request, Customer $customer): JsonResponse
    {
        $address = $customer->addresses()->create($request->validated());

        return (new CustomerAddressResource($address))->response()->setStatusCode(201);
    }

    public function update(CustomerAddressRequest $request, Customer $customer, CustomerAddress $address): CustomerAddressResource
    {
        $address->update($request->validated());

        return new CustomerAddressResource($address);
    }

    /**
     * Elimina la dirección si ningún pedido la usa.
     */
    public function destroy(Customer $customer, CustomerAddress $address): Response|JsonResponse
    {
        $inUse = $customer->orders()
            ->where(fn ($query) => $query->where('billing_address_id', $address->id)->orWhere('shipping_address_id', $address->id))
            ->exists();

        if ($inUse) {
            return response()->json(['message' => 'La dirección se usa en pedidos y no se puede eliminar.'], 409);
        }

        $address->delete();

        return response()->noContent();
    }
}
