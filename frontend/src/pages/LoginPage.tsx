import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { login } from '../services/auth';
import { useAuthStore } from '../stores/authStore';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const setSession = useAuthStore((s) => s.setSession);
  const navigate = useNavigate();

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const res = await login(email, password);
      const businessId = res.data.businesses?.[0]?.id || res.data.business?.id;
      setSession(res.data.user, res.data.token, res.data.businesses, businessId);
      navigate('/');
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
          ?.response?.data?.message ||
        (err as { response?: { data?: { errors?: { email?: string[] } } } })?.response?.data
          ?.errors?.email?.[0] ||
        'Login failed. Check your credentials.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 p-4">
      <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
        <div className="mb-8 text-center">
          <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-teal-700 text-lg font-bold text-white">
            MS
          </div>
          <h1 className="text-2xl font-bold text-slate-900">Welcome back</h1>
          <p className="mt-1 text-sm text-slate-500">Sign in to CNMG STOCKS</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {error && (
            <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
          )}

          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              placeholder="you@business.com"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">Password</label>
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
              placeholder="••••••••"
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-teal-700 py-3 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:opacity-60"
          >
            {loading ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-slate-500">
          New business?{' '}
          <Link to="/register" className="font-medium text-teal-700 hover:underline">
            Create account
          </Link>
        </p>
      </div>
    </div>
  );
}
