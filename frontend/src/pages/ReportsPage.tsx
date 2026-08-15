import { useCallback, useEffect, useState } from 'react';
import { BarChart3, TrendingUp, Package, AlertTriangle } from 'lucide-react';
import {
  fetchProfitSummary,
  fetchBestSellers,
  fetchLowStockCount,
  type ProfitSummary,
  type BestSeller,
} from '../services/reports';

function money(n: string | number) {
  return parseFloat(String(n) || '0').toLocaleString('en-GH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

function daysAgo(n: number) {
  const d = new Date();
  d.setDate(d.getDate() - n);
  return d.toISOString().slice(0, 10);
}

export default function ReportsPage() {
  const [from, setFrom] = useState(daysAgo(30));
  const [to, setTo] = useState(today());
  const [profit, setProfit] = useState<ProfitSummary | null>(null);
  const [bestSellers, setBestSellers] = useState<BestSeller[]>([]);
  const [lowStock, setLowStock] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [p, b, l] = await Promise.all([
        fetchProfitSummary({ from: from + ' 00:00:00', to: to + ' 23:59:59' }),
        fetchBestSellers({ from: from + ' 00:00:00', to: to + ' 23:59:59', limit: 5 }),
        fetchLowStockCount(),
      ]);
      setProfit(p);
      setBestSellers(b);
      setLowStock(l);
    } catch {
      setError('Could not load reports.');
    } finally {
      setLoading(false);
    }
  }, [from, to]);

  useEffect(() => {
    load();
  }, [load]);

  const cards = profit
    ? [
        {
          label: 'Revenue',
          value: `GHS ${money(profit.revenue)}`,
          sub: `${profit.sales_count} sales`,
          icon: TrendingUp,
          color: 'bg-teal-50 text-teal-700',
        },
        {
          label: 'Gross Profit',
          value: `GHS ${money(profit.gross_profit)}`,
          sub: 'After COGS',
          icon: BarChart3,
          color: 'bg-emerald-50 text-emerald-700',
        },
        {
          label: 'Expenses',
          value: `GHS ${money(profit.expenses)}`,
          sub: 'Operating costs',
          icon: Package,
          color: 'bg-slate-100 text-slate-700',
        },
        {
          label: 'Net Profit',
          value: `GHS ${money(profit.net_profit)}`,
          sub: 'Gross − Expenses',
          icon: TrendingUp,
          color:
            parseFloat(profit.net_profit) >= 0
              ? 'bg-emerald-50 text-emerald-700'
              : 'bg-red-50 text-red-700',
        },
      ]
    : [];

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-xl font-bold text-slate-900">Reports</h1>
        <div className="flex flex-wrap items-center gap-2">
          <input
            type="date"
            value={from}
            onChange={(e) => setFrom(e.target.value)}
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
          <span className="text-slate-400">to</span>
          <input
            type="date"
            value={to}
            onChange={(e) => setTo(e.target.value)}
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
        </div>
      </div>

      {error && (
        <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
      )}

      {loading ? (
        <div className="py-16 text-center text-slate-500">Loading reports…</div>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
            {cards.map((c) => (
              <div key={c.label} className="rounded-xl bg-white p-4 shadow-sm">
                <div className={`mb-2 inline-flex rounded-lg p-2 ${c.color}`}>
                  <c.icon className="h-4 w-4" />
                </div>
                <div className="text-xs font-medium text-slate-500">{c.label}</div>
                <div className="mt-0.5 text-base font-bold tabular-nums text-slate-900">
                  {c.value}
                </div>
                <div className="text-xs text-slate-400">{c.sub}</div>
              </div>
            ))}
          </div>

          {lowStock > 0 && (
            <div className="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
              <AlertTriangle className="h-5 w-5 text-amber-600" />
              <div>
                <div className="text-sm font-semibold text-amber-900">
                  {lowStock} product{lowStock === 1 ? '' : 's'} low on stock
                </div>
                <div className="text-xs text-amber-700">
                  Check Inventory and restock soon.
                </div>
              </div>
            </div>
          )}

          <div className="rounded-xl bg-white p-5 shadow-sm">
            <h2 className="mb-3 text-sm font-semibold text-slate-700">Best sellers</h2>
            {bestSellers.length === 0 ? (
              <p className="text-sm text-slate-500">No sales in this period.</p>
            ) : (
              <ul className="divide-y divide-slate-100">
                {bestSellers.map((b, i) => (
                  <li key={b.product_id} className="flex items-center gap-3 py-2.5">
                    <span className="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">
                      {i + 1}
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-sm font-medium text-slate-900">
                        {b.product_name}
                      </div>
                      <div className="text-xs text-slate-500">
                        Qty {parseFloat(b.total_quantity).toLocaleString()}
                      </div>
                    </div>
                    <div className="text-right text-sm font-semibold tabular-nums text-slate-900">
                      GHS {money(b.total_revenue)}
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <p className="text-center text-xs text-slate-400">
            Profit uses historical cost at time of sale — not today’s product cost.
          </p>
        </>
      )}
    </div>
  );
}
