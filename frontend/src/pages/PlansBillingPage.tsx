import { useEffect, useState } from 'react';
import { fetchPlans, initializeBilling, verifyBilling } from '../services/plans';
import { fetchCurrentBusiness } from '../services/business';
import type { AdminPlan } from '../services/admin';
import type { Business } from '../types';
import { useAuthStore } from '../stores/authStore';

export default function PlansBillingPage() {
  const { businesses, currentBusinessId } = useAuthStore();

  const [plans, setPlans] = useState<AdminPlan[]>([]);
  const [liveBusiness, setLiveBusiness] = useState<Business | null>(null);
  const [loading, setLoading] = useState(true);
  const [billing, setBilling] = useState<'monthly' | 'yearly'>('monthly');
  const [selectedPlan, setSelectedPlan] = useState<AdminPlan | null>(null);
  const [processingPlanId, setProcessingPlanId] = useState<string | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const cachedBusiness =
    businesses.find((b) => b.id === currentBusinessId) || businesses[0];

  const currentBusiness = liveBusiness || cachedBusiness;
  const currentPlan = currentBusiness?.plan || 'free';

  const refreshBusiness = async () => {
    const result = await fetchCurrentBusiness();
    setLiveBusiness(result.data);

    useAuthStore.setState((state) => ({
      businesses: state.businesses.map((business) =>
        business.id === result.data.id
          ? { ...business, ...result.data }
          : business
      ),
    }));

    return result.data;
  };

  useEffect(() => {
    Promise.all([fetchPlans(), fetchCurrentBusiness()])
      .then(([planList, businessResult]) => {
        setPlans(planList);
        setLiveBusiness(businessResult.data);

        useAuthStore.setState((state) => ({
          businesses: state.businesses.map((business) =>
            business.id === businessResult.data.id
              ? { ...business, ...businessResult.data }
              : business
          ),
        }));
      })
      .catch(() => setError('Could not load pricing or business information.'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const reference = params.get('reference') || params.get('trxref');

    if (!reference) return;

    setProcessingPlanId('verifying');
    setMessage('Verifying your payment…');
    setError('');

    verifyBilling(reference)
      .then(async (result) => {
        localStorage.removeItem('mystocks_payment_reference');
        await refreshBusiness();
        setSelectedPlan(null);
        setMessage(result.message || 'Payment verified successfully.');
        window.history.replaceState({}, document.title, '/plans-billing');
      })
      .catch((err: any) => {
        setError(
          err?.response?.data?.message ||
            'Payment was received but could not be verified automatically. Please contact support before paying again.'
        );
      })
      .finally(() => setProcessingPlanId(null));
  }, []);

  const startPayment = async (plan: AdminPlan) => {
    if (processingPlanId) return;

    setProcessingPlanId(plan.id);
    setMessage('');
    setError('');

    let timeoutId: ReturnType<typeof setTimeout> | undefined;

    try {
      const timeoutPromise = new Promise<never>((_, reject) => {
        timeoutId = setTimeout(
          () => reject(new Error('Payment initialization timed out.')),
          20000
        );
      });

      const result = await Promise.race([
        initializeBilling(plan.slug, billing),
        timeoutPromise,
      ]);

      if (!result.authorization_url) {
        throw new Error('Paystack checkout URL was not returned.');
      }

      localStorage.setItem('mystocks_payment_reference', result.reference);
      window.location.assign(result.authorization_url);
    } catch (err: any) {
      setError(
        err?.response?.data?.message ||
          err?.message ||
          'Could not start payment. Please try again.'
      );
      setProcessingPlanId(null);
    } finally {
      if (timeoutId) clearTimeout(timeoutId);
    }
  };

  const cancelSelection = () => {
    if (processingPlanId) return;
    setSelectedPlan(null);
    setMessage('');
    setError('');
  };

  if (loading) {
    return (
      <div className="py-16 text-center text-slate-500">
        Loading plans…
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-lg space-y-5">
      <div>
        <h1 className="text-xl font-bold text-slate-900">Plans & Billing</h1>
        <p className="mt-1 text-sm text-slate-500">
          Choose the plan that fits your business.
        </p>
      </div>

      <section className="rounded-xl bg-teal-700 p-5 text-white shadow-sm">
        <div className="text-xs font-semibold uppercase tracking-wide text-teal-100">
          Current plan
        </div>
        <div className="mt-1 text-2xl font-bold capitalize">{currentPlan}</div>
        <div className="mt-1 text-sm text-teal-100">
          {currentBusiness?.name || 'Your business'}
        </div>
      </section>

      <div className="flex rounded-xl bg-slate-100 p-1">
        <button
          type="button"
          disabled={!!processingPlanId}
          onClick={() => setBilling('monthly')}
          className={`flex-1 rounded-lg py-2.5 text-sm font-semibold ${
            billing === 'monthly'
              ? 'bg-white text-teal-700 shadow-sm'
              : 'text-slate-500'
          } disabled:opacity-60`}
        >
          Monthly
        </button>
        <button
          type="button"
          disabled={!!processingPlanId}
          onClick={() => setBilling('yearly')}
          className={`flex-1 rounded-lg py-2.5 text-sm font-semibold ${
            billing === 'yearly'
              ? 'bg-white text-teal-700 shadow-sm'
              : 'text-slate-500'
          } disabled:opacity-60`}
        >
          Yearly
        </button>
      </div>

      {message && (
        <div className="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {message}
        </div>
      )}

      {error && (
        <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}

      <div className="space-y-4">
        {plans.map((plan) => {
          const isCurrent = plan.slug === currentPlan;
          const isEnterprise = plan.slug === 'enterprise';
          const isSelected = selectedPlan?.id === plan.id;
          const isProcessingThisPlan = processingPlanId === plan.id;
          const anotherPlanProcessing =
            !!processingPlanId && processingPlanId !== plan.id;
          const price = Number(
            billing === 'yearly' ? plan.price_yearly : plan.price_monthly
          );

          return (
            <section
              key={plan.id}
              className={`rounded-xl border bg-white p-5 shadow-sm ${
                plan.slug === 'business'
                  ? 'border-teal-400'
                  : 'border-slate-200'
              }`}
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-lg font-bold text-slate-900">
                      {plan.name}
                    </h2>
                    {plan.slug === 'business' && (
                      <span className="rounded-full bg-teal-700 px-2 py-1 text-[10px] font-bold text-white">
                        Recommended
                      </span>
                    )}
                  </div>
                  <p className="mt-1 text-sm text-slate-500">
                    {plan.description}
                  </p>
                </div>
              </div>

              <div className="mt-4">
                {isEnterprise ? (
                  <div className="text-2xl font-bold text-slate-900">Custom</div>
                ) : (
                  <>
                    <span className="text-3xl font-bold text-slate-900">
                      GHS {price.toLocaleString()}
                    </span>
                    <span className="text-sm text-slate-500">
                      /{billing === 'monthly' ? 'month' : 'year'}
                    </span>
                  </>
                )}
              </div>

              <div className="mt-4 grid grid-cols-3 gap-2 text-center">
                <div className="rounded-lg bg-slate-50 p-2">
                  <div className="text-xs text-slate-500">Products</div>
                  <div className="mt-1 text-sm font-bold text-slate-900">
                    {plan.max_products ?? 'Unlimited'}
                  </div>
                </div>
                <div className="rounded-lg bg-slate-50 p-2">
                  <div className="text-xs text-slate-500">Users</div>
                  <div className="mt-1 text-sm font-bold text-slate-900">
                    {plan.max_users ?? 'Unlimited'}
                  </div>
                </div>
                <div className="rounded-lg bg-slate-50 p-2">
                  <div className="text-xs text-slate-500">Branches</div>
                  <div className="mt-1 text-sm font-bold text-slate-900">
                    {plan.max_branches ?? 'Unlimited'}
                  </div>
                </div>
              </div>

              {isSelected && !isCurrent && !isEnterprise && (
                <div className="mt-4 rounded-xl border border-teal-200 bg-teal-50 p-4">
                  <div className="font-bold text-teal-900">Confirm upgrade</div>
                  <p className="mt-1 text-sm text-teal-800">
                    {plan.name} · GHS {price.toLocaleString()} /{' '}
                    {billing === 'yearly' ? 'year' : 'month'}
                  </p>

                  <div className="mt-3 grid grid-cols-2 gap-2">
                    <button
                      type="button"
                      disabled={!!processingPlanId}
                      onClick={cancelSelection}
                      className="rounded-lg border border-slate-300 bg-white py-2.5 text-sm font-semibold text-slate-700 disabled:opacity-50"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      disabled={anotherPlanProcessing}
                      onClick={() => void startPayment(plan)}
                      className="rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                    >
                      {isProcessingThisPlan
                        ? 'Opening payment…'
                        : 'Continue to payment'}
                    </button>
                  </div>
                </div>
              )}

              {isCurrent ? (
                <button
                  type="button"
                  disabled
                  className="mt-4 w-full rounded-lg bg-slate-100 py-2.5 text-sm font-semibold text-slate-500"
                >
                  Current Plan
                </button>
              ) : isEnterprise ? (
                <button
                  type="button"
                  disabled={!!processingPlanId}
                  onClick={() =>
                    setMessage(
                      'Enterprise subscriptions are handled through CNMG Technologies support.'
                    )
                  }
                  className="mt-4 w-full rounded-lg border border-teal-700 py-2.5 text-sm font-semibold text-teal-700 disabled:opacity-50"
                >
                  Contact us
                </button>
              ) : (
                <button
                  type="button"
                  disabled={!!processingPlanId}
                  onClick={() => {
                    setSelectedPlan(plan);
                    setMessage('');
                    setError('');
                  }}
                  className="mt-4 w-full rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                >
                  Upgrade to {plan.name}
                </button>
              )}
            </section>
          );
        })}
      </div>
    </div>
  );
}
