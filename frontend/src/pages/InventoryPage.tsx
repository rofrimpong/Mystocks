import { FormEvent, useCallback, useEffect, useState } from 'react';
import { AlertTriangle, Package, Search } from 'lucide-react';
import {
  fetchBalances,
  adjustStock,
  openingStock,
  type InventoryBalance,
} from '../services/inventory';
import { useOfflineStore } from '../stores/offlineStore';

function qty(n: string | number) {
  return parseFloat(String(n)).toLocaleString('en-GH', { maximumFractionDigits: 2 });
}

export default function InventoryPage() {
  const [balances, setBalances] = useState<InventoryBalance[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [lowStockOnly, setLowStockOnly] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Adjustment modal
  const [modal, setModal] = useState<'adjust' | 'opening' | null>(null);
  const [selected, setSelected] = useState<InventoryBalance | null>(null);
  const [direction, setDirection] = useState<'in' | 'out'>('in');
  const [quantity, setQuantity] = useState('');
  const [reason, setReason] = useState('');
  const [unitCost, setUnitCost] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const { isOnline, enqueue } = useOfflineStore();
  const branchId = localStorage.getItem('mystocks_branch_id') || undefined;

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetchBalances({
        search: search || undefined,
        low_stock: lowStockOnly || undefined,
        branch_id: branchId,
        per_page: 50,
      });
      setBalances(res.data);
    } catch {
      setMessage({ type: 'error', text: 'Could not load inventory.' });
    } finally {
      setLoading(false);
    }
  }, [search, lowStockOnly, branchId]);

  useEffect(() => {
    const t = setTimeout(load, 250);
    return () => clearTimeout(t);
  }, [load]);

  const openAdjust = (b: InventoryBalance) => {
    setSelected(b);
    setDirection('in');
    setQuantity('');
    setReason('');
    setUnitCost(b.average_cost || '');
    setModal('adjust');
  };

  const openOpening = (b: InventoryBalance) => {
    setSelected(b);
    setQuantity('');
    setReason('Opening stock');
    setUnitCost(b.average_cost || b.product ? '' : '');
    setModal('opening');
  };

  const closeModal = () => {
    setModal(null);
    setSelected(null);
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (!selected || !quantity || parseFloat(quantity) <= 0) return;
    if (modal === 'adjust' && !reason.trim()) {
      setMessage({ type: 'error', text: 'Reason is required for adjustments.' });
      return;
    }

    setSubmitting(true);
    setMessage(null);

    const payload = {
      product_id: selected.product_id,
      branch_id: selected.branch_id || branchId || '',
      quantity: parseFloat(quantity),
      unit_cost: unitCost ? parseFloat(unitCost) : undefined,
      reason: reason || undefined,
      direction,
    };

    try {
      if (!isOnline) {
        if (modal === 'adjust') {
          enqueue('inventory_adjustment', payload);
        } else {
          enqueue('opening_stock', {
            product_id: payload.product_id,
            branch_id: payload.branch_id,
            quantity: payload.quantity,
            unit_cost: payload.unit_cost,
            reason: payload.reason,
          });
        }
        setMessage({ type: 'success', text: 'Saved offline — will sync when online.' });
        closeModal();
        return;
      }

      if (modal === 'adjust') {
        await adjustStock({
          product_id: payload.product_id,
          branch_id: payload.branch_id,
          direction: payload.direction,
          quantity: payload.quantity,
          reason: reason,
          unit_cost: payload.unit_cost,
        });
        setMessage({ type: 'success', text: 'Stock adjusted successfully.' });
      } else {
        await openingStock({
          product_id: payload.product_id,
          branch_id: payload.branch_id,
          quantity: payload.quantity,
          unit_cost: payload.unit_cost,
          reason: payload.reason,
        });
        setMessage({ type: 'success', text: 'Opening stock recorded.' });
      }
      closeModal();
      load();
    } catch (err: unknown) {
      if (!(err as { response?: unknown })?.response) {
        // Network error → offline queue
        if (modal === 'adjust') enqueue('inventory_adjustment', payload);
        else enqueue('opening_stock', payload);
        setMessage({ type: 'success', text: 'Network issue — saved offline.' });
        closeModal();
      } else {
        const msg =
          (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
          'Operation failed.';
        setMessage({ type: 'error', text: msg });
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-xl font-bold text-slate-900">Inventory</h1>
        <div className="flex flex-wrap items-center gap-2">
          <label className="flex items-center gap-2 text-sm text-slate-600">
            <input
              type="checkbox"
              checked={lowStockOnly}
              onChange={(e) => setLowStockOnly(e.target.checked)}
              className="rounded border-slate-300 text-teal-700 focus:ring-teal-500"
            />
            Low stock only
          </label>
        </div>
      </div>

      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          type="search"
          placeholder="Search products…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full rounded-xl border border-slate-300 py-3 pl-10 pr-4 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
        />
      </div>

      {message && (
        <div
          className={`rounded-lg px-4 py-3 text-sm ${
            message.type === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'
          }`}
        >
          {message.text}
        </div>
      )}

      {loading ? (
        <div className="py-16 text-center text-slate-500">Loading inventory…</div>
      ) : balances.length === 0 ? (
        <div className="rounded-xl bg-white py-16 text-center text-slate-500 shadow-sm">
          <Package className="mx-auto mb-2 h-8 w-8 text-slate-300" />
          <p>No inventory balances yet.</p>
          <p className="mt-1 text-sm text-slate-400">
            Record opening stock or receive a purchase to get started.
          </p>
        </div>
      ) : (
        <div className="overflow-hidden rounded-xl bg-white shadow-sm">
          <ul className="divide-y divide-slate-100">
            {balances.map((b) => (
              <li key={b.id} className="flex items-center gap-3 px-4 py-3">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="font-medium text-slate-900 truncate">
                      {b.product?.name || 'Product'}
                    </span>
                    {b.is_low_stock && (
                      <span className="inline-flex items-center gap-0.5 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700">
                        <AlertTriangle className="h-3 w-3" />
                        Low
                      </span>
                    )}
                  </div>
                  <div className="text-xs text-slate-500">
                    {b.product?.sku || 'No SKU'} · Min: {qty(b.product?.minimum_stock_level || 0)}
                  </div>
                </div>
                <div className="text-right">
                  <div className="text-lg font-bold tabular-nums text-slate-900">
                    {qty(b.quantity)}
                  </div>
                  <div className="text-[10px] text-slate-400">{b.product?.unit}</div>
                </div>
                <div className="flex flex-col gap-1">
                  <button
                    onClick={() => openAdjust(b)}
                    className="rounded-lg bg-slate-100 px-2 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-200"
                  >
                    Adjust
                  </button>
                  <button
                    onClick={() => openOpening(b)}
                    className="rounded-lg bg-teal-50 px-2 py-1.5 text-[11px] font-medium text-teal-700 hover:bg-teal-100"
                  >
                    Opening
                  </button>
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Modal */}
      {modal && selected && (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-4 sm:items-center">
          <form
            onSubmit={handleSubmit}
            className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl"
          >
            <h2 className="text-lg font-bold text-slate-900">
              {modal === 'adjust' ? 'Adjust stock' : 'Opening stock'}
            </h2>
            <p className="mt-1 text-sm text-slate-500">{selected.product?.name}</p>

            {modal === 'adjust' && (
              <div className="mt-4 grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={() => setDirection('in')}
                  className={`rounded-lg py-2.5 text-sm font-medium ${
                    direction === 'in' ? 'bg-teal-700 text-white' : 'bg-slate-100 text-slate-600'
                  }`}
                >
                  Add (In)
                </button>
                <button
                  type="button"
                  onClick={() => setDirection('out')}
                  className={`rounded-lg py-2.5 text-sm font-medium ${
                    direction === 'out' ? 'bg-teal-700 text-white' : 'bg-slate-100 text-slate-600'
                  }`}
                >
                  Remove (Out)
                </button>
              </div>
            )}

            <div className="mt-4 space-y-3">
              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">Quantity</label>
                <input
                  type="number"
                  step="any"
                  min="0.0001"
                  required
                  value={quantity}
                  onChange={(e) => setQuantity(e.target.value)}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                  placeholder="0"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">
                  Unit cost (optional)
                </label>
                <input
                  type="number"
                  step="any"
                  min="0"
                  value={unitCost}
                  onChange={(e) => setUnitCost(e.target.value)}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">
                  Reason {modal === 'adjust' && <span className="text-red-500">*</span>}
                </label>
                <input
                  type="text"
                  required={modal === 'adjust'}
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                  placeholder="e.g. Damaged goods, stock take"
                />
              </div>
            </div>

            <div className="mt-5 flex gap-2">
              <button
                type="button"
                onClick={closeModal}
                className="flex-1 rounded-lg border border-slate-300 py-2.5 text-sm font-medium text-slate-700"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={submitting}
                className="flex-1 rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
              >
                {submitting ? 'Saving…' : isOnline ? 'Save' : 'Save offline'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
