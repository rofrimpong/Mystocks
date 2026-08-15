<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class ProfitService
{
    /**
     * Calculate profit metrics for a business over a date range.
     * Uses historical cost stored on sale_items (never current product cost).
     *
     * @return array{
     *     revenue: string,
     *     cost_of_goods_sold: string,
     *     gross_profit: string,
     *     expenses: string,
     *     net_profit: string,
     *     sales_count: int,
     *     from: string|null,
     *     to: string|null
     * }
     */
    public function summarize(string $businessId, ?string $from = null, ?string $to = null, ?string $branchId = null): array
    {
        $salesQuery = Sale::query()
            ->where('business_id', $businessId)
            ->where('status', 'completed');

        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
        }
        if ($from) {
            $salesQuery->where('sold_at', '>=', $from);
        }
        if ($to) {
            $salesQuery->where('sold_at', '<=', $to);
        }

        $salesAgg = $salesQuery->selectRaw('
            COALESCE(SUM(total), 0) as revenue,
            COALESCE(SUM(cost_of_goods), 0) as cogs,
            COALESCE(SUM(gross_profit), 0) as gross_profit,
            COUNT(*) as sales_count
        ')->first();

        $expensesQuery = Expense::query()
            ->where('business_id', $businessId);

        if ($branchId) {
            $expensesQuery->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        if ($from) {
            $expensesQuery->where('expense_date', '>=', $from);
        }
        if ($to) {
            $expensesQuery->where('expense_date', '<=', $to);
        }

        $totalExpenses = $expensesQuery->sum('amount');

        $revenue = $this->dec($salesAgg->revenue ?? 0);
        $cogs = $this->dec($salesAgg->cogs ?? 0);
        $grossProfit = $this->dec($salesAgg->gross_profit ?? 0);
        $expenses = $this->dec($totalExpenses);
        $netProfit = bcsub($grossProfit, $expenses, 4);

        return [
            'revenue' => $revenue,
            'cost_of_goods_sold' => $cogs,
            'gross_profit' => $grossProfit,
            'expenses' => $expenses,
            'net_profit' => $netProfit,
            'sales_count' => (int) ($salesAgg->sales_count ?? 0),
            'from' => $from,
            'to' => $to,
        ];
    }

    private function dec(string|float|int $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
