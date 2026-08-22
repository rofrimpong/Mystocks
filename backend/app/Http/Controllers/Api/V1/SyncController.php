<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\SyncBatchRequest;
use App\Models\Business;
use App\Models\SyncOperation;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        private readonly SyncService $syncService
    ) {}

    /**
     * Push a batch of offline operations for synchronization.
     * Each operation is processed independently (idempotent + conflict-aware).
     */
    public function push(SyncBatchRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');
        $data = $request->validated();

        $user = $request->user();
        $isOwner = $request->attributes->get('is_business_owner', false);
        $role = $request->attributes->get('business_role');

        foreach ($data['operations'] as $operation) {
            if ((string) $operation['origin_business_id'] !== (string) $business->id
                || (string) $operation['origin_user_id'] !== (string) $user->id) {
                return response()->json([
                    'message' => 'An offline operation belongs to a different business or user.',
                ], 422);
            }
        }

        if (! $user->isPlatformAdmin() && ! $isOwner) {
            $allowedByType = [
                'sale' => ['manager', 'cashier', 'salesperson'],
                'inventory_adjustment' => ['manager', 'inventory_officer'],
                'opening_stock' => ['manager', 'inventory_officer'],
            ];

            foreach ($data['operations'] as $operation) {
                $type = $operation['operation_type'] ?? null;
                $allowedRoles = $allowedByType[$type] ?? [];

                if (! in_array($role, $allowedRoles, true)) {
                    return response()->json([
                        'message' => sprintf(
                            'Your role does not permit the offline operation "%s".',
                            $type ?? 'unknown'
                        ),
                    ], 403);
                }
            }
        }

        $result = $this->syncService->processBatch([
            'business_id' => $business->id,
            'user_id' => $request->user()->id,
            'device_id' => $data['device_id'],
            'operations' => $data['operations'],
        ]);

        $synced = collect($result['results'])->where('status', 'synced')->count();
        $conflicts = collect($result['results'])->where('status', 'conflict')->count();
        $already = collect($result['results'])->where('status', 'already_synced')->count();
        $failed = collect($result['results'])->where('status', 'failed')->count();

        return response()->json([
            'message' => 'Sync batch processed.',
            'summary' => [
                'total' => count($result['results']),
                'synced' => $synced,
                'already_synced' => $already,
                'conflicts' => $conflicts,
                'failed' => $failed,
            ],
            'results' => $result['results'],
        ]);
    }

    /**
     * Get status of previous sync operations for this device / business.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = SyncOperation::where('business_id', $business->id)
            ->orderByDesc('created_at');

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->input('device_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $ops = $query->limit(100)->get()->map(fn ($op) => [
            'id' => $op->id,
            'idempotency_key' => $op->idempotency_key,
            'operation_type' => $op->operation_type,
            'status' => $op->status,
            'conflict_reason' => $op->conflict_reason,
            'retry_count' => $op->retry_count,
            'client_created_at' => $op->client_created_at?->toIso8601String(),
            'synced_at' => $op->synced_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $ops]);
    }
}
