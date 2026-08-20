import { FormEvent, useEffect, useState } from 'react';
import { Plus, UserCog } from 'lucide-react';
import { fetchBranches } from '../services/business';
import {
  createStaff,
  fetchStaff,
  StaffMember,
  StaffRole,
  updateStaff,
} from '../services/staff';
import type { Branch } from '../types';

const blank = {
  name: '',
  email: '',
  phone: '',
  password: '',
  branch_id: '',
  role: 'cashier' as StaffRole,
};

const roleLabel = (role: string) =>
  ({
    owner: 'Owner',
    manager: 'Manager',
    cashier: 'Cashier',
    salesperson: 'Salesperson',
    inventory_officer: 'Inventory Officer',
  })[role] || role;

export default function StaffPage() {
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [form, setForm] = useState({ ...blank });
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');

  const load = async () => {
    try {
      setLoading(true);
      const [staffRows, branchRows] = await Promise.all([
        fetchStaff(),
        fetchBranches(),
      ]);

      setStaff(staffRows);
      setBranches(branchRows);

      if (!form.branch_id && branchRows.length > 0) {
        setForm((current) => ({
          ...current,
          branch_id: branchRows[0].id,
        }));
      }
    } catch (err: any) {
      setMessage(
        err?.response?.data?.message || 'Could not load staff.'
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const openNew = () => {
    setForm({
      ...blank,
      branch_id: branches[0]?.id || '',
    });
    setMessage('');
    setShow(true);
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setMessage('');

    try {
      await createStaff({
        name: form.name,
        email: form.email,
        phone: form.phone || null,
        password: form.password,
        branch_id: form.branch_id,
        role: form.role,
      });

      setShow(false);
      setMessage('Staff member added successfully.');
      await load();
    } catch (err: any) {
      setMessage(
        err?.response?.data?.message || 'Could not add staff member.'
      );
    } finally {
      setSaving(false);
    }
  };

  const toggleStaff = async (member: StaffMember) => {
    try {
      setMessage('');

      await updateStaff(member.id, {
        is_active: !member.is_active,
      });

      setMessage(
        member.is_active
          ? 'Staff member suspended.'
          : 'Staff member activated.'
      );

      await load();
    } catch (err: any) {
      setMessage(
        err?.response?.data?.message ||
          'Could not update staff member.'
      );
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900">
            Staff
          </h1>
          <p className="text-sm text-slate-500">
            Manage your team and branch assignments.
          </p>
        </div>

        <button
          onClick={openNew}
          className="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white"
        >
          <Plus className="h-4 w-4" />
          Add Staff
        </button>
      </div>

      {message && (
        <div className="rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700">
          {message}
        </div>
      )}

      {loading ? (
        <div className="py-12 text-center text-slate-500">
          Loading staff…
        </div>
      ) : (
        <div className="overflow-hidden rounded-xl bg-white shadow-sm">
          <div className="divide-y divide-slate-100">
            {staff.map((member) => (
              <div
                key={member.id}
                className="flex items-center gap-3 p-4"
              >
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-teal-50 text-teal-700">
                  <UserCog className="h-5 w-5" />
                </div>

                <div className="min-w-0 flex-1">
                  <div className="font-medium text-slate-900">
                    {member.name}
                  </div>

                  <div className="truncate text-xs text-slate-500">
                    {member.email}
                  </div>

                  <div className="mt-1 text-xs font-medium text-slate-600">
                    {roleLabel(member.role)}
                    {' · '}
                    {member.is_active ? 'Active' : 'Suspended'}
                  </div>
                </div>

                {!member.is_owner && (
                  <button
                    onClick={() => toggleStaff(member)}
                    className={`rounded-lg px-3 py-2 text-xs font-semibold ${
                      member.is_active
                        ? 'bg-red-50 text-red-700'
                        : 'bg-emerald-50 text-emerald-700'
                    }`}
                  >
                    {member.is_active ? 'Suspend' : 'Activate'}
                  </button>
                )}
              </div>
            ))}

            {staff.length === 0 && (
              <div className="p-8 text-center text-sm text-slate-500">
                No staff members yet.
              </div>
            )}
          </div>
        </div>
      )}

      {show && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-black/40 p-4 pb-24 sm:flex sm:items-center sm:justify-center sm:pb-4">
          <form
            onSubmit={submit}
            className="mx-auto w-full max-w-lg rounded-2xl bg-white p-5 pb-8 shadow-xl sm:my-auto"
          >
            <h2 className="text-lg font-bold text-slate-900">
              Add Staff
            </h2>

            {message && (
              <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {message}
              </div>
            )}

            <div className="mt-4 grid gap-3">
              <input
                required
                placeholder="Full name *"
                className="rounded-lg border px-3 py-2.5"
                value={form.name}
                onChange={(e) =>
                  setForm({ ...form, name: e.target.value })
                }
              />

              <input
                required
                type="email"
                placeholder="Email *"
                className="rounded-lg border px-3 py-2.5"
                value={form.email}
                onChange={(e) =>
                  setForm({ ...form, email: e.target.value })
                }
              />

              <input
                placeholder="Phone"
                className="rounded-lg border px-3 py-2.5"
                value={form.phone}
                onChange={(e) =>
                  setForm({ ...form, phone: e.target.value })
                }
              />

              <input
                required
                type="password"
                minLength={8}
                placeholder="Temporary password *"
                className="rounded-lg border px-3 py-2.5"
                value={form.password}
                onChange={(e) =>
                  setForm({ ...form, password: e.target.value })
                }
              />

              <select
                required
                className="rounded-lg border px-3 py-2.5"
                value={form.branch_id}
                onChange={(e) =>
                  setForm({ ...form, branch_id: e.target.value })
                }
              >
                <option value="">Select branch</option>
                {branches.map((branch) => (
                  <option key={branch.id} value={branch.id}>
                    {branch.name}
                  </option>
                ))}
              </select>

              <select
                className="rounded-lg border px-3 py-2.5"
                value={form.role}
                onChange={(e) =>
                  setForm({
                    ...form,
                    role: e.target.value as StaffRole,
                  })
                }
              >
                <option value="manager">Manager</option>
                <option value="cashier">Cashier</option>
                <option value="salesperson">Salesperson</option>
                <option value="inventory_officer">
                  Inventory Officer
                </option>
              </select>
            </div>

            <div className="mt-5 flex gap-2">
              <button
                type="button"
                onClick={() => setShow(false)}
                className="flex-1 rounded-lg border py-2.5"
              >
                Cancel
              </button>

              <button
                disabled={saving || !form.branch_id}
                className="flex-1 rounded-lg bg-teal-700 py-2.5 font-semibold text-white disabled:opacity-50"
              >
                {saving ? 'Saving…' : 'Add Staff'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
