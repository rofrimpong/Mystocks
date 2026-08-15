<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Business;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = Customer::query()
            ->where('business_id', $business->id)
            ->orderBy('name');

        if ($request->boolean('active_only', false)) {
            $query->where('status', 'active');
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->boolean('with_balance', false)) {
            $query->where('outstanding_balance', '>', 0);
        }

        $customers = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => CustomerResource::collection($customers),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $data = $request->validated();
        $data['business_id'] = $business->id;

        $customer = Customer::create($data);

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $customer = Customer::where('business_id', $business->id)->findOrFail($id);

        return response()->json([
            'data' => new CustomerResource($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $customer = Customer::where('business_id', $business->id)->findOrFail($id);
        $customer->update($request->validated());

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => new CustomerResource($customer->fresh()),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $customer = Customer::where('business_id', $business->id)->findOrFail($id);

        if (bccomp((string) $customer->outstanding_balance, '0', 4) > 0) {
            return response()->json([
                'message' => 'Cannot delete customer with outstanding balance. Clear the balance first.',
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }
}
