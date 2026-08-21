import { useEffect, useMemo, useState } from 'react';
import { fetchPlans, initializeBilling, verifyBilling } from '../services/plans';
import type { AdminPlan } from '../services/admin';
import { useAuthStore } from '../stores/authStore';

export default function PlansBillingPage() {
  const { businesses, currentBusinessId } = useAuthStore();
  const [plans, setPlans] = useState<AdminPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [billing, setBilling] = useState<'monthly' | 'yearly'>('monthly');
  const [selectedPlan, setSelectedPlan] = useState<AdminPlan | null>(null);
  const [processing, setProcessing] = useState(false);
  const [message, setMessage] = useState('');

  const currentBusiness = useMemo(() => businesses.find((b) => b.id === currentBusinessId) || businesses[0], [businesses, currentBusinessId]);
  const currentPlan = currentBusiness?.plan || 'free';

  useEffect(() => {
    fetchPlans()
      .then(setPlans)
      .catch(() => setMessage("Could not load pricing plans."))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const reference = params.get("reference") || localStorage.getItem("mystocks_payment_reference");
    if (!reference) return;

    setProcessing(true);
    verifyBilling(reference)
      .then((result) => {
        localStorage.removeItem("mystocks_payment_reference");
        setMessage(result.message || "Payment verified successfully.");
        window.history.replaceState({}, document.title, "/plans-billing");
        setTimeout(() => window.location.reload(), 1200);
      })
      .catch((err: any) => {
        setMessage(err?.response?.data?.message || "Could not verify payment.");
      })
      .finally(() => setProcessing(false));
  }, []);

  const startPayment = async (plan: AdminPlan) => {
    setProcessing(true);
    setMessage("");
    try {
      const result = await initializeBilling(plan.slug, billing);
      localStorage.setItem("mystocks_payment_reference", result.reference);
      window.location.href = result.authorization_url;
    } catch (err: any) {
      setMessage(err?.response?.data?.message || "Could not start payment.");
      setProcessing(false);
    }
  };


  if (loading) {
    return <div className="py-16 text-center text-slate-500">Loading plans…</div>;
  }

  return (
    <div className="mx-auto max-w-lg space-y-5">
      <div>
        <h1 className="text-xl font-bold text-slate-900">Plans & Billing</h1>
        <p className="mt-1 text-sm text-slate-500">Choose the plan that fits your business.</p>
      </div>
      <section className="rounded-xl bg-teal-700 p-5 text-white shadow-sm">
        <div className="text-xs font-semibold uppercase tracking-wide text-teal-100">Current plan</div>
        <div className="mt-1 text-2xl font-bold capitalize">{currentPlan}</div>
        <div className="mt-1 text-sm text-teal-100">{currentBusiness?.name || 'Your business'}</div>
      </section>
      <div className="flex rounded-xl bg-slate-100 p-1">
        <button type="button" onClick={() => setBilling('monthly')} className={`flex-1 rounded-lg py-2.5 text-sm font-semibold ${billing === 'monthly' ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-500'}`}>Monthly</button>
        <button type="button" onClick={() => setBilling('yearly')} className={`flex-1 rounded-lg py-2.5 text-sm font-semibold ${billing === 'yearly' ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-500'}`}>Yearly</button>
      </div>
      {message && <div className="rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700">{message}</div>}
      <div className="space-y-4">
        {plans.map((plan) => {
            const isCurrent = plan.slug === currentPlan;
          const price = Number(billing === 'yearly' ? plan.price_yearly : plan.price_monthly);
          return (
            <section key={plan.id} className={`rounded-xl border bg-white p-5 shadow-sm ${plan.slug === 'business' ? 'border-teal-400' : 'border-slate-200'}`}>
              <div className="flex items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-lg font-bold text-slate-900">{plan.name}</h2>
                    {plan.slug === 'business' && <span className="rounded-full bg-teal-700 px-2 py-1 text-[10px] font-bold text-white">Recommended</span>}
                  </div>
                  <p className="mt-1 text-sm text-slate-500">{plan.description}</p>
                </div>
              </div>
              <div className="mt-4">
                {plan.slug === 'enterprise' ? <div className="text-2xl font-bold text-slate-900">Custom</div> : <><span className="text-3xl font-bold text-slate-900">GHS {price.toLocaleString()}</span><span className="text-sm text-slate-500">/{billing === 'monthly' ? 'month' : 'year'}</span></>}
              </div>
              <div className="mt-4 grid grid-cols-3 gap-2 text-center">
                <div className="rounded-lg bg-slate-50 p-2"><div className="text-xs text-slate-500">Products</div><div className="mt-1 text-sm font-bold text-slate-900">{plan.max_products ?? 'Unlimited'}</div></div>
                <div className="rounded-lg bg-slate-50 p-2"><div className="text-xs text-slate-500">Users</div><div className="mt-1 text-sm font-bold text-slate-900">{plan.max_users ?? 'Unlimited'}</div></div>
                <div className="rounded-lg bg-slate-50 p-2"><div className="text-xs text-slate-500">Branches</div><div className="mt-1 text-sm font-bold text-slate-900">{plan.max_branches ?? 'Unlimited'}</div></div>
              </div>
              {selectedPlan?.id === plan.id && (
                <div className="mt-4 rounded-xl border border-teal-200 bg-teal-50 p-4">
                  <div className="font-bold text-teal-900">Confirm upgrade</div>
                  <p className="mt-1 text-sm text-teal-800">
                    {plan.name} · GHS {Number(billing === 'yearly' ? plan.price_yearly : plan.price_monthly).toLocaleString()} / {billing === 'yearly' ? 'year' : 'month'}
                  </p>
                  <div className="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" onClick={() => setSelectedPlan(null)} className="rounded-lg border border-slate-300 bg-white py-2.5 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="button" onClick={() => void startPayment(plan)} className="rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white">{processing ? 'Opening payment…' : 'Continue to payment'}</button>
                  </div>
                </div>
              )}
              {isCurrent ? <button type="button" disabled className="mt-4 w-full rounded-lg bg-slate-100 py-2.5 text-sm font-semibold text-slate-500">Current Plan</button> : plan.slug === 'enterprise' ? <button type="button" onClick={() => setMessage('Enterprise subscriptions will be handled through CNMG Technologies support.')} className="mt-4 w-full rounded-lg border border-teal-700 py-2.5 text-sm font-semibold text-teal-700">Contact us</button> : <button type="button" onClick={() => setSelectedPlan(plan)} className="mt-4 w-full rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white">Upgrade to {plan.name}</button>}
            </section>
          );
        })}
      </div>
    </div>
  );
}
