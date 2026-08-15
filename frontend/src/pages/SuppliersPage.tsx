import { FormEvent, useCallback, useEffect, useState } from 'react';
import { Plus, Search, Truck } from 'lucide-react';
import { fetchSuppliers, createSupplier, type Supplier } from '../services/suppliers';

function money(n: string | number) {
  return parseFloat(String(n)).toLocaleString('en-GH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

export default function SuppliersPage() {
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState({
    name: '',
    company: '',
    phone: '',
    email: '',
    address: '',
  });

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetchSuppliers({
        search: search || undefined,
        per_page: 50,
      });
      setSuppliers(res.data);
    } catch {
      setMessage({ type: 'error', text: 'Could not load suppliers.' });
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => {
    const t = setTimeout(load, 250);
    return () => clearTimeout(t);
  }, [load]);

  const update = (key: string, value: string) => setForm((f) => ({ ...f, [key]: value }));

  const handleCreate = async (e: FormEvent) => {
    e.preventDefault();
    if (!form.name.trim()) return;
    setSubmitting(true);
    setMessage(null);
    try {
      await createSupplier({
        name: form.name.trim(),
        company: form.company || undefined,
        phone: form.phone || undefined,
        email: form.email || undefined,
        address: form.address || undefined,
      });
      setMessage({ type: 'success', text: 'Supplier added successfully.' });
      setForm({ name: '', company: '', phone: '', email: '', address: '' });
      setShowForm(false);
      load();
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'Could not create supplier.';
      setMessage({ type: 'error', text: msg });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-bold text-slate-900">Suppliers</h1>
        <button
          onClick={() => setShowForm(true)}
          className="flex items-center gap-1.5 rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white"
        >
          <Plus className="h-4 w-4" />
          Add
        </button>
      </div>

      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          type="search"
          placeholder="Search name, company, phone…"
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
        <div className="py-16 text-center text-slate-500">Loading suppliers…</div>
      ) : suppliers.length === 0 ? (
        <div className="rounded-xl bg-white py-16 text-center text-slate-500 shadow-sm">
          <Truck className="mx-auto mb-2 h-8 w-8 text-slate-300" />
          <p>No suppliers yet.</p>
          <p className="mt-1 text-sm text-slate-400">Add suppliers to track purchases and balances.</p>
        </div>
      ) : (
        <div className="overflow-hidden rounded-xl bg-white shadow-sm">
          <ul className="divide-y divide-slate-100">
            {suppliers.map((s) => {
              const balance = parseFloat(s.outstanding_balance || '0');
              return (
                <li key={s.id} className="flex items-center gap-3 px-4 py-3">
                  <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-600">
                    {s.name.slice(0, 1).toUpperCase()}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="font-medium text-slate-900 truncate">{s.name}</div>
                    <div className="text-xs text-slate-500">
                      {s.company || s.phone || s.email || 'No contact'}
                    </div>
                  </div>
                  <div className="text-right">
                    {balance > 0 ? (
                      <>
                        <div className="text-sm font-semibold tabular-nums text-amber-700">
                          GHS {money(balance)}
                        </div>
                        <div className="text-[10px] text-amber-600">Payable</div>
                      </>
                    ) : (
                      <div className="text-xs text-slate-400">No balance</div>
                    )}
                  </div>
                </li>
              );
            })}
          </ul>
        </div>
      )}

      {showForm && (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-4 sm:items-center">
          <form
            onSubmit={handleCreate}
            className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl"
          >
            <h2 className="text-lg font-bold text-slate-900">Add supplier</h2>
            <div className="mt-4 space-y-3">
              <input
                required
                placeholder="Name *"
                value={form.name}
                onChange={(e) => update('name', e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              />
              <input
                placeholder="Company"
                value={form.company}
                onChange={(e) => update('company', e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              />
              <input
                placeholder="Phone"
                value={form.phone}
                onChange={(e) => update('phone', e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              />
              <input
                type="email"
                placeholder="Email"
                value={form.email}
                onChange={(e) => update('email', e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              />
              <input
                placeholder="Address"
                value={form.address}
                onChange={(e) => update('address', e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              />
            </div>
            <div className="mt-5 flex gap-2">
              <button
                type="button"
                onClick={() => setShowForm(false)}
                className="flex-1 rounded-lg border border-slate-300 py-2.5 text-sm font-medium text-slate-700"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={submitting}
                className="flex-1 rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
              >
                {submitting ? 'Saving…' : 'Save supplier'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
