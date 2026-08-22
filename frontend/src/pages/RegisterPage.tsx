import { FormEvent, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { register } from '../services/auth';
import { useAuthStore } from '../stores/authStore';

export default function RegisterPage() {
  const [searchParams] = useSearchParams();
  const referralFromLink = (searchParams.get('ref') || '').trim().toUpperCase();

  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    business_name: '',
    referral_code: referralFromLink,
  });

  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const setSession = useAuthStore((s) => s.setSession);
  const navigate = useNavigate();

  const update = (key: string, value: string) =>
    setForm((current) => ({ ...current, [key]: value }));

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');

    if (form.password !== form.password_confirmation) {
      setError('Passwords do not match.');
      return;
    }

    setLoading(true);

    try {
      const payload = {
        ...form,
        referral_code: form.referral_code.trim() || undefined,
        device_name: 'web',
      };

      const res = await register(payload);
      const businessId = res.data.business?.id;

      setSession(res.data.user, res.data.token, undefined, businessId);

      if (res.data.branch?.id) {
        localStorage.setItem('mystocks_branch_id', res.data.branch.id);
      }

      navigate('/');
    } catch (err: unknown) {
      const data = (
        err as {
          response?: {
            data?: {
              message?: string;
              errors?: Record<string, string[]>;
            };
          };
        }
      )?.response?.data;

      const firstError = data?.errors
        ? Object.values(data.errors).flat()[0]
        : null;

      setError(firstError || data?.message || 'Registration failed.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 p-4">
      <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
        <div className="mb-6 text-center">
          <img
            src="/pwa-192x192.png"
            alt="CNMG STOCKS"
            className="mx-auto mb-3 h-16 w-16 rounded-2xl object-cover shadow-sm"
          />
          <h1 className="text-2xl font-bold text-slate-900">
            Start your business
          </h1>
          <p className="mt-1 text-sm text-slate-500">
            Free plan • Ghana-ready
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-3">
          {error && (
            <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
              {error}
            </div>
          )}

          {referralFromLink && (
            <div className="rounded-lg bg-teal-50 px-4 py-3 text-sm text-teal-800">
              You were invited to CNMG STOCKS with referral code{' '}
              <strong>{referralFromLink}</strong>.
            </div>
          )}

          <input
            required
            placeholder="Your full name"
            value={form.name}
            onChange={(e) => update('name', e.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
          />

          <input
            required
            type="email"
            placeholder="Email"
            value={form.email}
            onChange={(e) => update('email', e.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
          />

          <input
            required
            placeholder="Phone number"
            value={form.phone}
            onChange={(e) => update('phone', e.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
          />

          <input
            required
            placeholder="Business name"
            value={form.business_name}
            onChange={(e) => update('business_name', e.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
          />

          <div>
            <input
              placeholder="Referral code (optional)"
              value={form.referral_code}
              onChange={(e) =>
                update('referral_code', e.target.value.toUpperCase())
              }
              autoCapitalize="characters"
              className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm uppercase outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
            />
            <p className="mt-1 px-1 text-xs text-slate-400">
              Enter the code of the person who invited you.
            </p>
          </div>

          <input
            required
            type="password"
            placeholder="Password"
            value={form.password}
            onChange={(e) => update('password', e.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
          />

          <input
            required
            type="password"
            placeholder="Confirm password"
            value={form.password_confirmation}
            onChange={(e) => update('password_confirmation', e.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
          />

          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-teal-700 py-3 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:opacity-60"
          >
            {loading ? 'Creating account…' : 'Create account'}
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-slate-500">
          Already have an account?{' '}
          <Link
            to="/login"
            className="font-medium text-teal-700 hover:underline"
          >
            Sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
