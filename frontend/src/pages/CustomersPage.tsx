import { FormEvent, useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Plus, Search, User, Edit3, BookOpen, X, Printer, MessageCircle } from 'lucide-react';
import { fetchCustomers, updateCustomer, createCustomer, fetchCustomerTransactions, recordCustomerPayment, recordOpeningBalance, recordCustomerAdjustment, type Customer, type CustomerTransaction } from '../services/customers';
import { useAuthStore } from '../stores/authStore';

const money = (n: string | number) => Number(n || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const waNumber = (phone?: string | null) => { const d = (phone || '').replace(/\D/g, ''); return d.startsWith('0') ? `233${d.slice(1)}` : d; };

export default function CustomersPage() {
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<Customer | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [ledgerCustomer, setLedgerCustomer] = useState<Customer | null>(null);
  const [transactions, setTransactions] = useState<CustomerTransaction[]>([]);
  const [ledgerLoading, setLedgerLoading] = useState(false);
  const [entry, setEntry] = useState({ mode: 'payment', amount: '', method: 'cash', reference: '', direction: 'increase', notes: '' });
  const [form, setForm] = useState({ name: '', phone: '', email: '', address: '', credit_limit: '' });
  const { currentBusinessRole, isBusinessOwner, user } = useAuthStore();
  const canAdjust = !!user?.is_platform_admin || isBusinessOwner || currentBusinessRole === 'owner' || currentBusinessRole === 'manager';

  const load = useCallback(async () => {
    setLoading(true);
    try { setCustomers((await fetchCustomers({ search: search || undefined, per_page: 50 })).data); }
    catch { setMessage({ type: 'error', text: 'Could not load customers.' }); }
    finally { setLoading(false); }
  }, [search]);
  useEffect(() => { const t = setTimeout(load, 250); return () => clearTimeout(t); }, [load]);

  const loadLedger = async (customer: Customer) => {
    setLedgerCustomer(customer); setLedgerLoading(true);
    try { setTransactions((await fetchCustomerTransactions(customer.id)).data); }
    catch { setMessage({ type: 'error', text: 'Could not load customer statement.' }); }
    finally { setLedgerLoading(false); }
  };
  const openEdit = (c: Customer) => { setEditing(c); setForm({ name: c.name, phone: c.phone || '', email: c.email || '', address: c.address || '', credit_limit: c.credit_limit || '' }); setShowForm(true); };
  const toggleCustomer = async (c: Customer) => { try { await updateCustomer(c.id, { status: c.status === 'active' ? 'inactive' : 'active' }); await load(); } catch (e: any) { setMessage({ type: 'error', text: e?.response?.data?.message || 'Could not update customer.' }); } };

  const saveCustomer = async (e: FormEvent) => {
    e.preventDefault(); setSubmitting(true);
    try {
      const payload = { name: form.name.trim(), phone: form.phone || undefined, email: form.email || undefined, address: form.address || undefined, credit_limit: form.credit_limit ? Number(form.credit_limit) : undefined };
      if (editing) await updateCustomer(editing.id, payload); else await createCustomer(payload);
      setMessage({ type: 'success', text: editing ? 'Customer updated.' : 'Customer added.' }); setShowForm(false); await load();
    } catch (e: any) { setMessage({ type: 'error', text: e?.response?.data?.message || 'Could not save customer.' }); }
    finally { setSubmitting(false); }
  };

  const postEntry = async (e: FormEvent) => {
    e.preventDefault(); if (!ledgerCustomer) return; setSubmitting(true);
    try {
      let c: Customer;
      if (entry.mode === 'payment') c = await recordCustomerPayment(ledgerCustomer.id, { amount: Number(entry.amount), payment_method: entry.method as 'cash' | 'mobile_money' | 'card' | 'bank_transfer', reference: entry.reference || undefined });
      else if (entry.mode === 'opening') c = await recordOpeningBalance(ledgerCustomer.id, { amount: Number(entry.amount), notes: entry.notes || undefined });
      else c = await recordCustomerAdjustment(ledgerCustomer.id, { direction: entry.direction as 'increase' | 'decrease', amount: Number(entry.amount), notes: entry.notes });
      setEntry({ mode: 'payment', amount: '', method: 'cash', reference: '', direction: 'increase', notes: '' }); setMessage({ type: 'success', text: 'Customer ledger updated.' }); await loadLedger(c); await load();
    } catch (e: any) { setMessage({ type: 'error', text: e?.response?.data?.message || Object.values(e?.response?.data?.errors || {}).flat().join(' ') || 'Could not update ledger.' }); }
    finally { setSubmitting(false); }
  };

  const typeName = (t: string) => ({ sale: 'Credit sale', payment: 'Payment', credit_note: 'Credit note', opening_balance: 'Opening balance', adjustment: 'Adjustment' }[t] || t);
  return <div className="space-y-4">
    <div className="flex items-center justify-between"><h1 className="text-xl font-bold">Customers</h1><button onClick={() => { setEditing(null); setForm({ name: '', phone: '', email: '', address: '', credit_limit: '' }); setShowForm(true); }} className="flex items-center gap-1 rounded-lg bg-teal-700 px-3 py-2 text-sm text-white"><Plus className="h-4 w-4" />Add</button></div>
    <div className="relative"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search name, phone, email…" className="w-full rounded-xl border py-3 pl-10 pr-4 text-sm" /></div>
    {message && <div className={`rounded-lg px-4 py-3 text-sm ${message.type === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'}`}>{message.text}</div>}
    {loading ? <div className="py-16 text-center text-slate-500">Loading customers…</div> : customers.length === 0 ? <div className="rounded-xl bg-white py-16 text-center text-slate-500"><User className="mx-auto h-8 w-8" />No customers yet.</div> : <div className="overflow-hidden rounded-xl bg-white shadow-sm"><ul className="divide-y">{customers.map((c) => <li key={c.id} className="flex items-center gap-3 p-4"><div className="flex h-10 w-10 items-center justify-center rounded-full bg-teal-50 font-bold text-teal-700">{c.name[0]?.toUpperCase()}</div><div className="min-w-0 flex-1"><div className="truncate font-medium">{c.name}</div><div className="text-xs text-slate-500">{c.phone || c.email || 'No contact'}</div><div className={`text-sm font-semibold ${Number(c.outstanding_balance) > 0 ? 'text-amber-700' : 'text-slate-400'}`}>Owes GHS {money(c.outstanding_balance)}</div></div><div className="flex flex-col gap-1"><button onClick={() => loadLedger(c)} className="flex items-center gap-1 rounded-lg bg-teal-50 px-2 py-1.5 text-xs font-semibold text-teal-700"><BookOpen className="h-3.5 w-3.5" />Ledger</button><button onClick={() => openEdit(c)} className="rounded-lg bg-slate-100 p-2"><Edit3 className="h-4 w-4" /></button><button onClick={() => toggleCustomer(c)} className="text-[10px] text-slate-500">{c.status === 'active' ? 'Deactivate' : 'Activate'}</button></div></li>)}</ul></div>}

    {showForm && <div className="fixed inset-0 z-40 overflow-y-auto bg-black/40 p-4 pb-24 sm:flex sm:items-center sm:justify-center"><form onSubmit={saveCustomer} className="mx-auto w-full max-w-md rounded-2xl bg-white p-5"><h2 className="text-lg font-bold">{editing ? 'Edit customer' : 'Add customer'}</h2><div className="mt-4 space-y-3">{[['name','Full name *'],['phone','Phone'],['email','Email'],['address','Address'],['credit_limit','Credit limit (0 = unlimited)']].map(([k,p]) => <input key={k} required={k === 'name'} type={k === 'email' ? 'email' : k === 'credit_limit' ? 'number' : 'text'} min={k === 'credit_limit' ? '0' : undefined} step={k === 'credit_limit' ? '0.01' : undefined} placeholder={p} value={form[k as keyof typeof form]} onChange={(e) => setForm((f) => ({ ...f, [k]: e.target.value }))} className="w-full rounded-lg border px-3 py-2.5 text-sm" />)}</div><div className="mt-5 flex gap-2"><button type="button" onClick={() => setShowForm(false)} className="flex-1 rounded-lg border py-2.5">Cancel</button><button disabled={submitting} className="flex-1 rounded-lg bg-teal-700 py-2.5 font-semibold text-white">{submitting ? 'Saving…' : 'Save'}</button></div></form></div>}

    {ledgerCustomer && createPortal(<div className="statement-portal fixed inset-0 z-50 overflow-y-auto bg-black/40 p-3 pb-24"><div className="statement-print mx-auto my-3 max-w-2xl rounded-2xl bg-white p-5"><div className="flex justify-between"><div><h2 className="text-xl font-bold">Customer statement</h2><p className="font-semibold text-teal-700">{ledgerCustomer.name}</p><p className="text-xs text-slate-500">{ledgerCustomer.phone || ledgerCustomer.email}</p></div><button onClick={() => setLedgerCustomer(null)}><X /></button></div><div className="my-4 rounded-xl bg-amber-50 p-4"><div className="text-xs text-amber-700">Outstanding balance</div><div className="text-2xl font-bold text-amber-800">GHS {money(ledgerCustomer.outstanding_balance)}</div></div>
      <div className="statement-actions mb-4 flex gap-2"><button onClick={() => window.print()} className="flex gap-1 rounded-lg bg-slate-100 px-3 py-2 text-sm"><Printer className="h-4 w-4" />Print</button>{waNumber(ledgerCustomer.phone) && <a target="_blank" rel="noreferrer" href={`https://wa.me/${waNumber(ledgerCustomer.phone)}?text=${encodeURIComponent(`Hello ${ledgerCustomer.name}, your current balance is GHS ${money(ledgerCustomer.outstanding_balance)}.`)}`} className="flex gap-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm text-white"><MessageCircle className="h-4 w-4" />WhatsApp</a>}</div>
      <div className="overflow-x-auto rounded-xl border"><table className="w-full text-xs"><thead className="bg-slate-50"><tr><th className="p-2 text-left">Date</th><th className="p-2 text-left">Entry</th><th className="p-2 text-right">Amount</th><th className="p-2 text-right">Balance</th></tr></thead><tbody>{ledgerLoading ? <tr><td colSpan={4} className="p-6 text-center">Loading…</td></tr> : transactions.length === 0 ? <tr><td colSpan={4} className="p-6 text-center text-slate-400">No ledger activity</td></tr> : transactions.map((t) => <tr key={t.id} className="border-t"><td className="whitespace-nowrap p-2">{new Date(t.occurred_at).toLocaleDateString('en-GB')}</td><td className="p-2">{typeName(t.type)}<div className="text-[10px] text-slate-400">{t.notes}</div></td><td className={`p-2 text-right font-semibold ${Number(t.amount) < 0 ? 'text-emerald-700' : ''}`}>GHS {money(t.amount)}</td><td className="p-2 text-right">GHS {money(t.balance_after)}</td></tr>)}</tbody></table></div>
      <form onSubmit={postEntry} className="statement-actions mt-5 rounded-xl bg-slate-50 p-4"><div className="grid grid-cols-3 gap-2">{['payment', ...(transactions.length === 0 && Number(ledgerCustomer.outstanding_balance) === 0 ? ['opening'] : []), ...(canAdjust ? ['adjustment'] : [])].map((m) => <button type="button" key={m} onClick={() => setEntry((x) => ({ ...x, mode: m }))} className={`rounded-lg py-2 text-xs font-semibold capitalize ${entry.mode === m ? 'bg-teal-700 text-white' : 'bg-white'}`}>{m}</button>)}</div><div className="mt-3 grid gap-2 sm:grid-cols-2"><input required type="number" min="0.01" step="0.01" placeholder="Amount" value={entry.amount} onChange={(e) => setEntry((x) => ({ ...x, amount: e.target.value }))} className="rounded-lg border px-3 py-2.5 text-sm" />{entry.mode === 'payment' && <select value={entry.method} onChange={(e) => setEntry((x) => ({ ...x, method: e.target.value }))} className="rounded-lg border px-3 py-2.5 text-sm"><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option></select>}{entry.mode === 'adjustment' && <select value={entry.direction} onChange={(e) => setEntry((x) => ({ ...x, direction: e.target.value }))} className="rounded-lg border px-3 py-2.5 text-sm"><option value="increase">Increase</option><option value="decrease">Decrease</option></select>}<input placeholder={entry.mode === 'payment' ? 'Reference (optional)' : 'Reason / notes'} required={entry.mode === 'adjustment'} value={entry.mode === 'payment' ? entry.reference : entry.notes} onChange={(e) => setEntry((x) => entry.mode === 'payment' ? { ...x, reference: e.target.value } : { ...x, notes: e.target.value })} className="rounded-lg border px-3 py-2.5 text-sm sm:col-span-2" /></div><button disabled={submitting} className="mt-3 w-full rounded-lg bg-teal-700 py-2.5 font-semibold text-white">{submitting ? 'Saving…' : entry.mode === 'payment' ? 'Record payment' : 'Post entry'}</button></form>
    </div></div>, document.body)}
    <style>{`@media print { body > *:not(.statement-portal){display:none!important}.statement-portal{position:static!important;background:white!important;padding:0!important}.statement-print{margin:0!important;max-width:none!important}.statement-actions,.statement-print button{display:none!important}@page{margin:10mm}}`}</style>
  </div>;
}
