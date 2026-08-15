<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\ProfitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ProfitService $profitService
    ) {}

    /**
     * Profit summary: Revenue, COGS, Gross Profit, Expenses, Net Profit.
     * Uses historical cost stored on sales.
     */
    public function profitSummary(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $from = $request->input('from');
        $to = $request->input('to');
        $branchId = $request->input('branch_id');

        $summary = $this->profitService->summarize(
            businessId: $business->id,
            from: $from,
            to: $to,
            branchId: $branchId
        );

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * Today's quick stats (for dashboard).
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $today = now()->toDateString();
        $branchId = $request->input('branch_id') ?? $request->attributes->get('current_branch_id');

        $todaySummary = $this->profitService->summarize(
            businessId: $business->id,
            from: $today . ' 00:00:00',
            to: $today . ' 23:59:59',
            branchId: $branchId
        );

        return response()->json([
            'data' => [
                'today' => $todaySummary,
                'business' => [
                    'id' => $business->id,
                    'name' => $business->name,
                    'currency' => $business->currency,
                ],
            ],
        ]);
    }
}
