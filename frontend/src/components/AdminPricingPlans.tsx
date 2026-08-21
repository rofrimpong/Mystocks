import { useEffect, useState } from 'react';
import { getAdminPlans, updateAdminPlan, type AdminPlan } from '../services/admin';

export default function AdminPricingPlans() {
  const [plans, setPlans] = useState<AdminPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [savingId, setSavingId] = useState<string | null>(null);
  const [message, setMessage] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      setPlans(await getAdminPlans());
    } catch {
      setMessage('Could not load pricing plans.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { void load(); }, []);

  const change = (id: string, key: keyof AdminPlan, value: string | number | boolean | null) => {
    setPlans((items) => items.map((plan) => plan.id === id ? { ...plan, [key]: value } : plan));
  };

  const save = async (plan: AdminPlan) => {
    setSavingId(plan.id);
    setMessage('');
    try {
      const updated = await updateAdminPlan(plan.id, {
        name: plan.name,
        description: plan.description,
        price_monthly: Number(plan.price_monthly),
        price_yearly: Number(plan.price_yearly),
        max_products: plan.max_products,
        max_users: plan.max_users,
        max_branches: plan.max_branches,
        is_active: plan.is_active,
      });
      setPlans((items) => items.map((item) => item.id === updated.id ? updated : item));
      setMessage(plan.name + ' plan updated successfully.');
    } catch (err: any) {
      setMessage(err?.response?.data?.message || 'Could not update pricing plan.');
    } finally {
      setSavingId(null);
    }
  };

  if (loading) {
    return <section className="rounded-xl bg-white p-4 shadow-sm"><h2 className="font-bold">Pricing Plans</h2><div className="py-8 text-center text-slate-500">Loading pricing plans…</div></section>;
  }

  return (
    <section className="rounded-xl bg-white p-4 shadow-sm">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="font-bold">Pricing Plans</h2>
          <p className="text-xs text-slate-500">Edit prices and limits shown to CNMG STOCKS customers.</p>
        </div>
      </div>

      {message && <div className="mt-3 rounded-lg bg-slate-100 px-3 py-2 text-sm">{message}</div>}

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        {plans.map((plan) => (
          <div key={plan.id} className={`rounded-xl border p-4 ${plan.slug === 'business' ? 'border-teal-300 bg-teal-50/40' : 'border-slate-200'}`}>
            <div className="flex items-start justify-between gap-3">
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <input value={plan.name} onChange={(e) => change(plan.id, 'name', e.target.value)} className="w-32 rounded-lg border px-2 py-1.5 text-sm font-semibold" />
                  {plan.slug === 'business' && <span className="rounded-full bg-teal-700 px-2 py-0.5 text-[10px] font-bold text-white">Recommended</span>}
                </div>
                <div className="mt-1 text-xs uppercase tracking-wide text-slate-400">{plan.slug}</div>
              </div>
              <label className="flex items-center gap-2 text-xs font-medium text-slate-600">
                <input type="checkbox" checked={plan.is_active} onChange={(e) => change(plan.id, 'is_active', e.target.checked)} /> Active
              </label>
            </div>
            <textarea value={plan.description || ''} onChange={(e) => change(plan.id, 'description', e.target.value)} placeholder="Plan description" className="mt-3 w-full rounded-lg border px-3 py-2 text-sm" rows={2} />
            <div className="mt-3 grid grid-cols-2 gap-2">
              <label className="text-xs font-medium text-slate-600">Monthly (GHS)<input type="number" min="0" step="0.01" value={plan.price_monthly} onChange={(e) => change(plan.id, 'price_monthly', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" /></label>
              <label className="text-xs font-medium text-slate-600">Yearly (GHS)<input type="number" min="0" step="0.01" value={plan.price_yearly} onChange={(e) => change(plan.id, 'price_yearly', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" /></label>
            </div>
            <div className="mt-3 grid grid-cols-3 gap-2">
              <label className="text-xs font-medium text-slate-600">Products<input type="number" min="1" placeholder="Unlimited" value={plan.max_products ?? ''} onChange={(e) => change(plan.id, 'max_products', e.target.value === '' ? null : Number(e.target.value))} className="mt-1 w-full rounded-lg border px-2 py-2 text-sm" /></label>
              <label className="text-xs font-medium text-slate-600">Users<input type="number" min="1" placeholder="Unlimited" value={plan.max_users ?? ''} onChange={(e) => change(plan.id, 'max_users', e.target.value === '' ? null : Number(e.target.value))} className="mt-1 w-full rounded-lg border px-2 py-2 text-sm" /></label>
              <label className="text-xs font-medium text-slate-600">Branches<input type="number" min="1" placeholder="Unlimited" value={plan.max_branches ?? ''} onChange={(e) => change(plan.id, 'max_branches', e.target.value === '' ? null : Number(e.target.value))} className="mt-1 w-full rounded-lg border px-2 py-2 text-sm" /></label>
            </div>
            <button type="button" onClick={() => void save(plan)} disabled={savingId === plan.id} className="mt-4 w-full rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{savingId === plan.id ? 'Saving…' : 'Save plan'}</button>
          </div>
        ))}
      </div>
    </section>
  );
}
