<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    private function business(Request $request): Business
    {
        return $request->attributes->get('current_business');
    }

    private function guard(Request $request): void
    {
        abort_unless(
            $request->attributes->get('is_business_owner', false)
            || $request->user()?->isPlatformAdmin()
            || $request->attributes->get('business_role') === 'manager',
            403,
            'Only the business owner or a manager can manage staff.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->guard($request);
        $business = $this->business($request);

        $staff = $business->users()
            ->withPivot(['branch_id', 'is_owner', 'role', 'is_active'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'branch_id' => $user->pivot->branch_id,
                'is_owner' => (bool) $user->pivot->is_owner,
                'role' => $user->pivot->is_owner ? 'owner' : $user->pivot->role,
                'is_active' => (bool) $user->pivot->is_active,
            ]);

        return response()->json(['data' => $staff]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guard($request);
        $business = $this->business($request);

        $userLimit = $business->userLimit();

        if ($userLimit !== null) {
            $currentUsers = $business->users()
                ->wherePivot('is_active', true)
                ->count();

            if ($currentUsers >= $userLimit) {
                return response()->json([
                    'message' => sprintf(
                        'Your %s plan allows a maximum of %d active users.',
                        ucfirst($business->plan ?? 'trial'),
                        $userLimit
                    ),
                ], 422);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'branch_id' => ['required', 'uuid'],
            'role' => [
                'required',
                Rule::in(['manager', 'cashier', 'salesperson', 'inventory_officer']),
            ],
        ]);

        $branch = $business->branches()
            ->where('id', $data['branch_id'])
            ->where('status', 'active')
            ->first();

        if (! $branch) {
            return response()->json([
                'message' => 'The selected branch does not belong to this business or is inactive.',
            ], 422);
        }

        if (User::where('email', $data['email'])->exists()) {
            return response()->json([
                'message' => 'A user with this email address already exists.',
            ], 422);
        }

        $user = DB::transaction(function () use ($data, $business) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $business->users()->attach($user->id, [
                'branch_id' => $data['branch_id'],
                'is_owner' => false,
                'role' => $data['role'],
                'is_active' => true,
            ]);

            return $user;
        });

        return response()->json([
            'message' => 'Staff member created successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'branch_id' => $data['branch_id'],
                'role' => $data['role'],
                'is_active' => true,
            ],
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->guard($request);
        $business = $this->business($request);

        $user = $business->users()->where('users.id', $id)->firstOrFail();

        if ($user->pivot->is_owner) {
            return response()->json([
                'message' => 'The business owner cannot be modified from Staff Management.',
            ], 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'branch_id' => ['sometimes', 'required', 'uuid'],
            'role' => [
                'sometimes',
                Rule::in(['manager', 'cashier', 'salesperson', 'inventory_officer']),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['branch_id'])) {
            $validBranch = $business->branches()
                ->where('id', $data['branch_id'])
                ->where('status', 'active')
                ->exists();

            if (! $validBranch) {
                return response()->json([
                    'message' => 'The selected branch does not belong to this business or is inactive.',
                ], 422);
            }
        }

        if (
            array_key_exists('is_active', $data)
            && $data['is_active']
            && ! $user->pivot->is_active
        ) {
            $userLimit = $business->userLimit();

            if (
                $userLimit !== null
                && $business->users()->wherePivot('is_active', true)->count() >= $userLimit
            ) {
                return response()->json([
                    'message' => sprintf(
                        'Your %s plan allows a maximum of %d active users.',
                        ucfirst($business->plan ?? 'trial'),
                        $userLimit
                    ),
                ], 422);
            }
        }

        $userData = [];

        foreach (['name', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $userData[$field] = $data[$field];
            }
        }

        if (! empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }

        if ($userData) {
            $user->update($userData);
        }

        $pivotData = [];

        foreach (['branch_id', 'role', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $pivotData[$field] = $data[$field];
            }
        }

        if ($pivotData) {
            $business->users()->updateExistingPivot($user->id, $pivotData);
        }

        return response()->json([
            'message' => 'Staff member updated successfully.',
        ]);
    }
}
