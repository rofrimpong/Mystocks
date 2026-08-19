import { FormEvent, useEffect, useState } from 'react';
import { Building2, GitBranch, LogOut, User } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import {
  fetchBusinesses,
  fetchCurrentBusiness,
  fetchBranches,
  updateBusiness,
} from '../services/business';
import { useAuthStore } from '../stores/authStore';
import type { Business, Branch } from '../types';

export default function SettingsPage() {
  const { user, logout, currentBusinessId, setCurrentBusiness } = useAuthStore();
  const navigate = useNavigate();

  const [businesses, setBusinesses] = useState<Business[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [business, setBusiness] = useState<Business | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const [form, setForm] = useState({
    name: '',
    phone: '',
    email: '',
    address: '',
    city: '',
    region: '',
  });

  useEffect(() => {
    async function load() {
      setLoading(true);
      try {
        const [bizList, current] = await Promise.all([
          fetchBusinesses(),
          fetchCurrentBusiness().catch(() => null),
        ]);
        setBusinesses(bizList);

        if (current?.data) {
          setBusiness(current.data);
          setForm({
            name: current.data.name || '',
            phone: (current.data as Business & { phone?: string }).phone || '',
            email: (current.data as Business & { email?: string }).email || '',
            address: (current.data as Business & { address?: string }).address || '',
            city: (current.data as Business & { city?: string }).city || '',
            region: (current.data as Business & { region?: string }).region || '',
          });
        } else if (bizList[0]) {
          setBusiness(bizList[0]);
        }

        const branchList = await fetchBranches().catch(() => []);
        setBranches(branchList);
      } catch {
        setMessage({ type: 'error', text: 'Could not load settings.' });
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [currentBusinessId]);

  const update = (key: string, value: string) => setForm((f) => ({ ...f, [key]: value }));

  const handleSave = async (e: FormEvent) => {
    e.preventDefault();
    if (!business?.id) return;
    setSaving(true);
    setMessage(null);
    try {
      await updateBusiness(business.id, {
        name: form.name,
        phone: form.phone || undefined,
        email: form.email || undefined,
        address: form.address || undefined,
        city: form.city || undefined,
        region: form.region || undefined,
      });
      setMessage({ type: 'success', text: 'Business profile updated.' });
    } catch {
      setMessage({ type: 'error', text: 'Could not update business. You may need owner access.' });
    } finally {
      setSaving(false);
    }
  };

  const switchBusiness = (id: string) => {
    setCurrentBusiness(id);
    setMessage({ type: 'success', text: 'Business switched. Reloading context…' });
    // Force refresh of dependent data
    window.location.reload();
  };

  const switchBranch = (id: string) => {
    localStorage.setItem('mystocks_branch_id', id);
    setMessage({ type: 'success', text: 'Branch selected.' });
  };

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  if (loading) {
    return <div className="py-16 text-center text-slate-500">Loading settings…</div>;
  }

  const currentBranchId = localStorage.getItem('mystocks_branch_id');

  return (
    <div className="mx-auto max-w-lg space-y-6">
      <h1 className="text-xl font-bold text-slate-900">Settings</h1>

      {message && (
        <div
          className={`rounded-lg px-4 py-3 text-sm ${
            message.type === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'
          }`}
        >
          {message.text}
        </div>
      )}

      {/* Account */}
      <section className="rounded-xl bg-white p-5 shadow-sm">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
          <User className="h-4 w-4" />
          Account
        </div>
        <div className="text-sm text-slate-900">{user?.name}</div>
        <div className="text-xs text-slate-500">{user?.email}</div>
      </section>

      {/* Business switcher */}
      {businesses.length > 1 && (
        <section className="rounded-xl bg-white p-5 shadow-sm">
          <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
            <Building2 className="h-4 w-4" />
            Business
          </div>
          <div className="space-y-2">
            {businesses.map((b) => (
              <button
                key={b.id}
                onClick={() => switchBusiness(b.id)}
                className={`flex w-full items-center justify-between rounded-lg border px-3 py-2.5 text-left text-sm ${
                  (currentBusinessId || business?.id) === b.id
                    ? 'border-teal-600 bg-teal-50 text-teal-900'
                    : 'border-slate-200 text-slate-700'
                }`}
              >
                <span className="font-medium">{b.name}</span>
                <span className="text-xs text-slate-500">{b.currency}</span>
              </button>
            ))}
          </div>
        </section>
      )}

      {/* Branch switcher */}
      {branches.length > 0 && (
        <section className="rounded-xl bg-white p-5 shadow-sm">
          <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
            <GitBranch className="h-4 w-4" />
            Branch
          </div>
          <div className="space-y-2">
            {branches.map((br) => (
              <button
                key={br.id}
                onClick={() => switchBranch(br.id)}
                className={`flex w-full items-center justify-between rounded-lg border px-3 py-2.5 text-left text-sm ${
                  currentBranchId === br.id
                    ? 'border-teal-600 bg-teal-50 text-teal-900'
                    : 'border-slate-200 text-slate-700'
                }`}
              >
                <span className="font-medium">{br.name}</span>
                {br.is_head_office && (
                  <span className="text-[10px] text-slate-400">Head office</span>
                )}
              </button>
            ))}
          </div>
        </section>
      )}

      {/* Business profile */}
      <section className="rounded-xl bg-white p-5 shadow-sm">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
          <Building2 className="h-4 w-4" />
          Business profile
        </div>
        <form onSubmit={handleSave} className="space-y-3">
          <input
            required
            placeholder="Business name"
            value={form.name}
            onChange={(e) => update('name', e.target.value)}
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
          <div className="grid grid-cols-2 gap-2">
            <input
              placeholder="City"
              value={form.city}
              onChange={(e) => update('city', e.target.value)}
              className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600"
            />
            <input
              placeholder="Region"
              value={form.region}
              onChange={(e) => update('region', e.target.value)}
              className="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-teal-600"
            />
          </div>
          <button
            type="submit"
            disabled={saving}
            className="w-full rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
          >
            {saving ? 'Saving…' : 'Save changes'}
          </button>
        </form>
      </section>

      <button
        onClick={handleLogout}
        className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white py-3 text-sm font-medium text-slate-700 shadow-sm"
      >
        <LogOut className="h-4 w-4" />
        Log out
      </button>

      <p className="text-center text-xs text-slate-400">
        CNMG STOCKS · CNMG Technologies · Ghana & Africa
      </p>
    </div>
  );
}
