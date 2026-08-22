<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Business;
use App\Models\Customer;
use App\Services\CustomerLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerLedgerService $ledgerService) {}

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
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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

    public function transactions(Request $request, string $id): JsonResponse
    {
        $business = $request->attributes->get('current_business');
        $customer = Customer::where('business_id', $business->id)->findOrFail($id);
        $transactions = $customer->transactions()
            ->with('creator:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->paginate(min($request->integer('per_page', 50), 100));

        return response()->json([
            'data' => $transactions->getCollection()->map(fn ($transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => (string) $transaction->amount,
                'balance_after' => (string) $transaction->balance_after,
                'payment_method' => $transaction->payment_method,
                'payment_reference' => $transaction->payment_reference,
                'notes' => $transaction->notes,
                'created_by' => $transaction->creator ? [
                    'id' => $transaction->creator->id,
                    'name' => $transaction->creator->name,
                ] : null,
                'occurred_at' => $transaction->occurred_at?->toIso8601String(),
            ]),
            'meta' => ['total' => $transactions->total()],
            'customer' => new CustomerResource($customer->fresh()),
        ]);
    }

    public function payment(Request $request, string $id): JsonResponse
    {
        $business = $request->attributes->get('current_business');
        $customer = Customer::where('business_id', $business->id)->where('status', 'active')->findOrFail($id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'payment_method' => ['required', 'in:cash,mobile_money,card,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['nullable', 'date'],
        ]);
        $transaction = $this->ledgerService->recordPayment($customer, $data, $request->user()->id);
        return response()->json([
            'message' => 'Customer payment recorded successfully.',
            'data' => ['transaction_id' => $transaction->id, 'customer' => new CustomerResource($customer->fresh())],
        ], 201);
    }

    public function openingBalance(Request $request, string $id): JsonResponse
    {
        $business = $request->attributes->get('current_business');
        $customer = Customer::where('business_id', $business->id)->where('status', 'active')->findOrFail($id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'notes' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['nullable', 'date'],
        ]);
        $transaction = $this->ledgerService->recordOpeningBalance($customer, $data, $request->user()->id);
        return response()->json([
            'message' => 'Opening balance recorded successfully.',
            'data' => ['transaction_id' => $transaction->id, 'customer' => new CustomerResource($customer->fresh())],
        ], 201);
    }

    public function adjustment(Request $request, string $id): JsonResponse
    {
        $business = $request->attributes->get('current_business');
        $customer = Customer::where('business_id', $business->id)->findOrFail($id);
        $data = $request->validate([
            'direction' => ['required', 'in:increase,decrease'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'notes' => ['required', 'string', 'max:500'],
            'occurred_at' => ['nullable', 'date'],
        ]);
        $transaction = $this->ledgerService->recordAdjustment($customer, $data, $request->user()->id);
        return response()->json([
            'message' => 'Customer balance adjusted successfully.',
            'data' => ['transaction_id' => $transaction->id, 'customer' => new CustomerResource($customer->fresh())],
        ], 201);
    }
}
