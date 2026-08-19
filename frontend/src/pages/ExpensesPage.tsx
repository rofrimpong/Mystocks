import { FormEvent, useCallback, useEffect, useState } from 'react';
import { Plus, Receipt } from 'lucide-react';
import {
  fetchExpenses,
  fetchExpenseCategories,
  createExpense,
  createExpenseCategory,
  type Expense,
  type ExpenseCategory,
} from '../services/expenses';

function money(n: string | number) {
  return parseFloat(String(n)).toLocaleString('en-GH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

export default function ExpensesPage() {
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [categories, setCategories] = useState<ExpenseCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState({
    category_id: '',
    amount: '',
    description: '',
    payment_method: 'cash',
    expense_date: new Date().toISOString().slice(0, 10),
    new_category: '',
  });

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [expRes, catRes] = await Promise.all([
        fetchExpenses({ per_page: 50 }),
        fetchExpenseCategories(),
      ]);
      setExpenses(expRes.data);
      setCategories(catRes);
      if (catRes.length && !form.category_id) {
        setForm((f) => ({ ...f, category_id: catRes[0].id }));
      }
    } catch {
      setMessage({ type: 'error', text: 'Could not load expenses.' });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const update = (key: string, value: string) => setForm((f) => ({ ...f, [key]: value }));

  const handleCreate = async (e: FormEvent) => {
    e.preventDefault();
    if (!form.amount || parseFloat(form.amount) <= 0) return;

    setSubmitting(true);
    setMessage(null);

    try {
      let categoryId = form.category_id;

      // Create category on the fly if user typed a new one and none selected
      if (form.new_category.trim() && !categoryId) {
        const res = await createExpenseCategory(form.new_category.trim());
        categoryId = res.data?.id || res.data?.data?.id;
        await load();
      }

      if (!categoryId) {
        setMessage({ type: 'error', text: 'Select or create a category.' });
        setSubmitting(false);
        return;
      }

      await createExpense({
        category_id: categoryId,
        amount: parseFloat(form.amount),
        description: form.description || undefined,
        payment_method: form.payment_method,
        expense_date: form.expense_date,
      });

      setMessage({ type: 'success', text: 'Expense recorded.' });
      setForm({
        category_id: categoryId,
        amount: '',
        description: '',
        payment_method: 'cash',
        expense_date: new Date().toISOString().slice(0, 10),
        new_category: '',
      });
      setShowForm(false);
      load();
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'Could not save expense.';
      setMessage({ type: 'error', text: msg });
    } finally {
      setSubmitting(false);
    }
  };

  const total = expenses.reduce((s, e) => s + parseFloat(e.amount || '0'), 0);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900">Expenses</h1>
          {expenses.length > 0 && (
            <p className="text-sm text-slate-500">
              Listed total: <span className="font-semibold tabular-nums">GHS {money(total)}</span>
            </p>
          )}
        </div>
        <button
          onClick={() => setShowForm(true)}
          className="flex items-center gap-1.5 rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white"
        >
          <Plus className="h-4 w-4" />
          Add
        </button>
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
        <div className="py-16 text-center text-slate-500">Loading expenses…</div>
      ) : expenses.length === 0 ? (
        <div className="rounded-xl bg-white py-16 text-center text-slate-500 shadow-sm">
          <Receipt className="mx-auto mb-2 h-8 w-8 text-slate-300" />
          <p>No expenses yet.</p>
          <p className="mt-1 text-sm text-slate-400">Record rent, utilities, transport, etc.</p>
        </div>
      ) : (
        <div className="overflow-hidden rounded-xl bg-white shadow-sm">
          <ul className="divide-y divide-slate-100">
            {expenses.map((e) => (
              <li key={e.id} className="flex items-center gap-3 px-4 py-3">
                <div className="min-w-0 flex-1">
                  <div className="font-medium text-slate-900">
                    {e.category?.name || 'Expense'}
                  </div>
                  <div className="text-xs text-slate-500">
                    {e.expense_date}
                    {e.description ? ` · ${e.description}` : ''}
                    {e.payment_method ? ` · ${e.payment_method}` : ''}
                  </div>
                </div>
                <div className="text-sm font-bold tabular-nums text-slate-900">
                  GHS {money(e.amount)}
                </div>
              </li>
            ))}
          </ul>
        </div>
      )}

      {showForm && (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-4 sm:items-center">
          <form
            onSubmit={handleCreate}
            className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl"
          >
            <h2 className="text-lg font-bold text-slate-900">Record expense</h2>
            <div className="mt-4 space-y-3">
              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">Category</label>
                <select
                  value={form.category_id}
                  onChange={(e) => update('category_id', e.target.value)}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600"
                >
                  <option value="">Select category</option>
                  {categories.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
              </div>
              <input
                placeholder="Or type new category name"
                value={form.new_category}
                onChange={(e) => update('new_category', e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              />
              <input
                required
                type="number"
                min="0.01"
                step="any"
                placeholder="Amount (GHS) *"
                value={form.amount}
                onChange={(e) => update('amount', e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              />
              <input
                placeholder="Description"
                value={form.description}
                onChange={(e) => update('description', e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              />
              <div className="grid grid-cols-2 gap-2">
                <select
                  value={form.payment_method}
                  onChange={(e) => update('payment_method', e.target.value)}
                  className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                >
                  <option value="cash">Cash</option>
                  <option value="mobile_money">MoMo</option>
                  <option value="card">Card</option>
                  <option value="bank_transfer">Bank</option>
                  <option value="other">Other</option>
                </select>
                <input
                  type="date"
                  value={form.expense_date}
                  onChange={(e) => update('expense_date', e.target.value)}
                  className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                />
              </div>
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
                {submitting ? 'Saving…' : 'Save expense'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
