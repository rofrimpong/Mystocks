<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryBalanceResource;
use App\Http\Resources\ProductResource;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\ProfitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(
        private readonly ProfitService $profitService
    ) {}

    /**
     * Profit summary: Revenue, COGS, Gross Profit, Expenses, Net Profit.
     */
    public function profitSummary(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $summary = $this->profitService->summarize(
            businessId: $business->id,
            from: $request->input('from'),
            to: $request->input('to'),
            branchId: $request->input('branch_id')
        );

        return response()->json(['data' => $summary]);
    }

    /**
     * Dashboard – today's stats + low stock count + stock value.
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

        $balanceQuery = InventoryBalance::where('business_id', $business->id);
        if ($branchId) {
            $balanceQuery->where('branch_id', $branchId);
        }

        $stockValue = (clone $balanceQuery)->selectRaw('COALESCE(SUM(quantity * average_cost), 0) as value')->value('value');
        $lowStockCount = InventoryBalance::where('business_id', $business->id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('product', function ($q) {
                $q->where('track_inventory', true)
                    ->whereColumn('inventory_balances.quantity', '<=', 'products.minimum_stock_level');
            })
            ->count();

        $productCount = Product::where('business_id', $business->id)->where('is_active', true)->count();

        return response()->json([
            'data' => [
                'today' => $todaySummary,
                'stock_value' => number_format((float) $stockValue, 4, '.', ''),
                'low_stock_count' => $lowStockCount,
                'total_products' => $productCount,
                'business' => [
                    'id' => $business->id,
                    'name' => $business->name,
                    'currency' => $business->currency,
                ],
            ],
        ]);
    }

    /**
     * Best-selling products in a period.
     */
    public function bestSellers(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $from = $request->input('from');
        $to = $request->input('to');
        $limit = min($request->integer('limit', 10), 50);

        $query = SaleItem::query()
            ->select([
                'sale_items.product_id',
                'sale_items.product_name',
                'sale_items.product_sku',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.line_total) as total_revenue'),
                DB::raw('SUM(sale_items.line_profit) as total_profit'),
            ])
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $business->id)
            ->where('sales.status', 'completed')
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'sale_items.product_sku')
            ->orderByDesc('total_quantity')
            ->limit($limit);

        if ($from) {
            $query->where('sales.sold_at', '>=', $from);
        }
        if ($to) {
            $query->where('sales.sold_at', '<=', $to);
        }
        if ($request->filled('branch_id')) {
            $query->where('sales.branch_id', $request->input('branch_id'));
        }

        $results = $query->get()->map(fn ($row) => [
            'product_id' => $row->product_id,
            'product_name' => $row->product_name,
            'product_sku' => $row->product_sku,
            'total_quantity' => (string) $row->total_quantity,
            'total_revenue' => number_format((float) $row->total_revenue, 4, '.', ''),
            'total_profit' => number_format((float) $row->total_profit, 4, '.', ''),
        ]);

        return response()->json(['data' => $results]);
    }

    /**
     * Low-stock products.
     */
    public function lowStock(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = InventoryBalance::query()
            ->where('business_id', $business->id)
            ->with(['product', 'branch'])
            ->whereHas('product', function ($q) {
                $q->where('track_inventory', true)
                    ->where('is_active', true)
                    ->whereColumn('inventory_balances.quantity', '<=', 'products.minimum_stock_level');
            });

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $balances = $query->orderBy('quantity')->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => InventoryBalanceResource::collection($balances),
            'meta' => [
                'current_page' => $balances->currentPage(),
                'last_page' => $balances->lastPage(),
                'per_page' => $balances->perPage(),
                'total' => $balances->total(),
            ],
        ]);
    }

    /**
     * Inventory valuation (stock value at average cost).
     */
    public function inventoryValuation(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $query = InventoryBalance::query()
            ->where('business_id', $business->id)
            ->with('product')
            ->where('quantity', '>', 0);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $balances = $query->get();

        $totalValue = '0';
        $items = $balances->map(function ($b) use (&$totalValue) {
            $lineValue = bcmul((string) $b->quantity, (string) $b->average_cost, 4);
            $totalValue = bcadd($totalValue, $lineValue, 4);

            return [
                'product_id' => $b->product_id,
                'product_name' => $b->product?->name,
                'sku' => $b->product?->sku,
                'quantity' => (string) $b->quantity,
                'average_cost' => (string) $b->average_cost,
                'value' => $lineValue,
            ];
        });

        return response()->json([
            'data' => [
                'items' => $items,
                'total_value' => $totalValue,
                'currency' => $business->currency,
            ],
        ]);
    }

    /**
     * Sales summary by day (for charts).
     */
    public function salesByDay(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->attributes->get('current_business');

        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to = $request->input('to', now()->toDateString());

        $query = Sale::query()
            ->where('business_id', $business->id)
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw("DATE(sold_at) as date, COUNT(*) as sales_count, SUM(total) as revenue, SUM(gross_profit) as profit")
            ->groupBy(DB::raw('DATE(sold_at)'))
            ->orderBy('date');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $rows = $query->get()->map(fn ($r) => [
            'date' => $r->date,
            'sales_count' => (int) $r->sales_count,
            'revenue' => number_format((float) $r->revenue, 4, '.', ''),
            'profit' => number_format((float) $r->profit, 4, '.', ''),
        ]);

        return response()->json(['data' => $rows]);
    }
}
