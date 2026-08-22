import { useEffect, useState } from 'react';
import api from '../services/api';
import type { DashboardData } from '../types';
import { TrendingUp, Package, AlertTriangle, Wallet, HandCoins } from 'lucide-react';

function formatMoney(value: string | number, currency = 'GHS') {
  const num = typeof value === 'string' ? parseFloat(value) : value;
  return `${currency} ${num.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export default function DashboardPage() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    api
      .get<{ data: DashboardData }>('/reports/dashboard')
      .then((res) => setData(res.data.data))
      .catch(() => setError('Could not load dashboard.'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center text-slate-500">Loading dashboard…</div>
    );
  }

  if (error || !data) {
    return (
      <div className="rounded-xl bg-red-50 p-6 text-center text-red-700">
        {error || 'No data available.'}
      </div>
    );
  }

  const currency = data.business.currency || 'GHS';

  const cards = [
    {
      label: 'Customer Credit',
      value: formatMoney(data.customer_credit?.outstanding || 0, currency),
      sub: `${data.customer_credit?.customers || 0} customers owe`,
      icon: HandCoins,
      color: (data.customer_credit?.customers || 0) > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-50 text-slate-600',
    },
    {
      label: "Today's Sales",
      value: formatMoney(data.today.revenue, currency),
      sub: `${data.today.sales_count} transactions`,
      icon: TrendingUp,
      color: 'bg-teal-50 text-teal-700',
    },
    {
      label: "Today's Profit",
      value: formatMoney(data.today.gross_profit, currency),
      sub: 'Gross profit',
      icon: Wallet,
      color: 'bg-emerald-50 text-emerald-700',
    },
    {
      label: 'Stock Value',
      value: formatMoney(data.stock_value, currency),
      sub: `${data.total_products} products`,
      icon: Package,
      color: 'bg-blue-50 text-blue-700',
    },
    {
      label: 'Low Stock',
      value: String(data.low_stock_count),
      sub: 'Items need attention',
      icon: AlertTriangle,
      color: data.low_stock_count > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-50 text-slate-600',
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold text-slate-900 md:text-2xl">Dashboard</h1>
        <p className="text-sm text-slate-500">{data.business.name}</p>
      </div>

      <div className="grid grid-cols-2 gap-3 md:grid-cols-5 md:gap-4">
        {cards.map((card) => (
          <div key={card.label} className="rounded-xl bg-white p-4 shadow-sm">
            <div className={`mb-3 inline-flex rounded-lg p-2 ${card.color}`}>
              <card.icon className="h-5 w-5" />
            </div>
            <div className="text-xs font-medium text-slate-500">{card.label}</div>
            <div className="mt-0.5 text-lg font-bold tabular-nums text-slate-900 amount">
              {card.value}
            </div>
            <div className="mt-0.5 text-xs text-slate-400">{card.sub}</div>
          </div>
        ))}
      </div>

      <div className="rounded-xl bg-white p-5 shadow-sm">
        <h2 className="mb-3 text-sm font-semibold text-slate-700">Quick actions</h2>
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
          <a
            href="/sales"
            className="rounded-lg border border-slate-200 px-4 py-3 text-center text-sm font-medium text-slate-700 transition hover:border-teal-300 hover:bg-teal-50"
          >
            New Sale
          </a>
          <a
            href="/products"
            className="rounded-lg border border-slate-200 px-4 py-3 text-center text-sm font-medium text-slate-700 transition hover:border-teal-300 hover:bg-teal-50"
          >
            Products
          </a>
          <a
            href="/inventory"
            className="rounded-lg border border-slate-200 px-4 py-3 text-center text-sm font-medium text-slate-700 transition hover:border-teal-300 hover:bg-teal-50"
          >
            Inventory
          </a>
          <a
            href="/reports"
            className="rounded-lg border border-slate-200 px-4 py-3 text-center text-sm font-medium text-slate-700 transition hover:border-teal-300 hover:bg-teal-50"
          >
            Reports
          </a>
        </div>
      </div>
    </div>
  );
}
