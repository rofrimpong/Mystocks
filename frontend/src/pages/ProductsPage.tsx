import { useEffect, useState } from 'react';
import api from '../services/api';
import type { Product } from '../types';

export default function ProductsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  useEffect(() => {
    const params = search ? { search } : {};
    api
      .get<{ data: Product[] }>('/products', { params })
      .then((res) => setProducts(res.data.data))
      .finally(() => setLoading(false));
  }, [search]);

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-xl font-bold text-slate-900">Products</h1>
        <input
          type="search"
          placeholder="Search name, SKU, barcode…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm sm:w-72 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
        />
      </div>

      {loading ? (
        <div className="py-12 text-center text-slate-500">Loading products…</div>
      ) : products.length === 0 ? (
        <div className="rounded-xl bg-white py-12 text-center text-slate-500 shadow-sm">
          No products yet. Add your first product to get started.
        </div>
      ) : (
        <div className="overflow-hidden rounded-xl bg-white shadow-sm">
          <ul className="divide-y divide-slate-100">
            {products.map((p) => (
              <li key={p.id} className="flex items-center justify-between px-4 py-3">
                <div>
                  <div className="font-medium text-slate-900">{p.name}</div>
                  <div className="text-xs text-slate-500">
                    {p.sku || 'No SKU'} · {p.unit}
                  </div>
                </div>
                <div className="text-right">
                  <div className="font-semibold tabular-nums text-slate-900 amount">
                    GHS {parseFloat(p.selling_price).toFixed(2)}
                  </div>
                  <div className="text-xs text-slate-400">
                    Cost: {parseFloat(p.buying_price).toFixed(2)}
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
