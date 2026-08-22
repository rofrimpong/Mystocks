import { useCallback, useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { MessageCircle, Minus, Plus, Search, ShoppingCart, Trash2, X } from 'lucide-react';
import { fetchProducts, createSale, fetchSales, fetchSale, cancelSale, type CartItem, type CreateSalePayload } from '../services/sales';
import type { Product, Sale } from '../types';
import { useOfflineStore } from '../stores/offlineStore';
import { useAuthStore } from '../stores/authStore';
import { fetchBalances, type InventoryBalance } from '../services/inventory';
import { v4 as uuidv4 } from 'uuid';
import { fetchCustomers, type Customer } from '../services/customers';

function money(n: number) {
  return n.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function whatsAppNumber(phone?: string | null) {
  const digits = (phone || '').replace(/\D/g, '');
  if (!digits) return '';
  if (digits.startsWith('0')) return `233${digits.slice(1)}`;
  return digits;
}

function receiptWhatsAppText(sale: Sale, businessName: string) {
  const cancelled = sale.status === 'cancelled';
  const lines = (sale.items || []).map((item) =>
    `${item.product_name} — ${Number(item.quantity).toLocaleString('en-GH', { maximumFractionDigits: 2 })} × GHS ${money(Number(item.unit_selling_price))} = GHS ${money(Number(item.line_total))}`
  );
  const paymentMethod = sale.payments?.[0]?.method?.replace('_', ' ') || sale.payment_status;

  return [
    `*${businessName}*`,
    '*TRANSACTION RECEIPT*',
    `Receipt: ${sale.sale_number}`,
    `Status: ${cancelled ? 'CANCELLED' : 'COMPLETED'}`,
    `Date: ${sale.sold_at ? new Date(sale.sold_at).toLocaleString('en-GB') : '—'}`,
    sale.customer ? `Customer: ${sale.customer.name}${sale.customer.phone ? ` (${sale.customer.phone})` : ''}` : '',
    `Payment: ${paymentMethod}`,
    '',
    ...lines,
    '',
    `Subtotal: GHS ${money(Number(sale.subtotal))}`,
    Number(sale.discount_amount) > 0 ? `Discount: GHS ${money(Number(sale.discount_amount))}` : '',
    Number(sale.tax_amount) > 0 ? `Tax: GHS ${money(Number(sale.tax_amount))}` : '',
    `Grand total: GHS ${money(Number(sale.total))}`,
    cancelled ? '*CANCELLED — NO AMOUNT DUE*' : sale.payment_status === 'credit' ? `Balance due: GHS ${money(Number(sale.total))}` : 'Thank you for your business.',
  ].filter(Boolean).join('\n');
}

export default function SalesPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [balances, setBalances] = useState<InventoryBalance[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [cart, setCart] = useState<CartItem[]>([]);
  const [paymentMethod, setPaymentMethod] = useState<'cash' | 'mobile_money' | 'card' | 'credit'>('cash');
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [showCart, setShowCart] = useState(false);
  const [lastSale, setLastSale] = useState<Sale | null>(null);
  const [view, setView] = useState<'pos' | 'history'>('pos');
  const [salesHistory, setSalesHistory] = useState<Sale[]>([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [selectedCustomerId, setSelectedCustomerId] = useState('');

  const { isOnline, enqueue, syncAll } = useOfflineStore();
  const { businesses, currentBusinessId, currentBusinessRole, isBusinessOwner, user } = useAuthStore();
  const currentBusiness = businesses.find((b) => b.id === currentBusinessId) || businesses[0];
  const canCancelSales = !!user?.is_platform_admin || isBusinessOwner || currentBusinessRole === 'owner' || currentBusinessRole === 'manager';

  const loadProducts = useCallback(async (q?: string) => {
    try {
      const branchId = localStorage.getItem('mystocks_branch_id') || currentBusiness?.branch_id || undefined;
      const [list, balanceResponse] = await Promise.all([
        fetchProducts(q),
        fetchBalances({
          search: q || undefined,
          branch_id: branchId,
          per_page: 100,
        }),
      ]);

      setProducts(list);
      setBalances(balanceResponse.data);
    } catch {
      setMessage({ type: 'error', text: 'Could not load products.' });
    } finally {
      setLoading(false);
    }
  }, [currentBusiness?.branch_id]);

  useEffect(() => {
    loadProducts();
    fetchCustomers({ active_only: true, per_page: 100 }).then((r) => setCustomers(r.data)).catch(() => undefined);
  }, [loadProducts]);

  useEffect(() => {
    const t = setTimeout(() => loadProducts(search), 300);
    return () => clearTimeout(t);
  }, [search, loadProducts]);

  const addToCart = (product: Product) => {
    setCart((prev) => {
      const existing = prev.find((i) => i.product.id === product.id);
      if (existing) {
        return prev.map((i) =>
          i.product.id === product.id ? { ...i, quantity: i.quantity + 1 } : i
        );
      }
      return [
        ...prev,
        {
          product,
          quantity: 1,
          unit_selling_price: parseFloat(product.selling_price) || 0,
          discount_amount: 0,
        },
      ];
    });
    setShowCart(true);
  };

  const updateQty = (productId: string, delta: number) => {
    setCart((prev) =>
      prev
        .map((i) =>
          i.product.id === productId
            ? { ...i, quantity: Math.max(0, i.quantity + delta) }
            : i
        )
        .filter((i) => i.quantity > 0)
    );
  };

  const removeItem = (productId: string) => {
    setCart((prev) => prev.filter((i) => i.product.id !== productId));
  };

  const subtotal = useMemo(
    () => cart.reduce((sum, i) => sum + i.quantity * i.unit_selling_price - i.discount_amount, 0),
    [cart]
  );

  const cartCount = cart.reduce((n, i) => n + i.quantity, 0);
  const receiptItemCount = lastSale?.items?.reduce((total, item) => total + Number(item.quantity), 0) || 0;
  const receiptAmountPaid = lastSale?.payments?.reduce((total, payment) => total + Number(payment.amount), 0) || 0;
  const receiptBalance = lastSale ? Math.max(Number(lastSale.total) - receiptAmountPaid, 0) : 0;
  const receiptChange = lastSale ? Math.max(receiptAmountPaid - Number(lastSale.total), 0) : 0;
  const receiptCancelled = lastSale?.status === 'cancelled';
  const receiptWhatsAppUrl = lastSale
    ? `https://wa.me/${whatsAppNumber(lastSale.customer?.phone)}?text=${encodeURIComponent(receiptWhatsAppText(lastSale, currentBusiness?.name || 'CNMG STOCKS'))}`
    : '';
  const getStock = (p: Product) => Number(balances.find((b) => b.product_id === p.id)?.available_quantity ?? balances.find((b) => b.product_id === p.id)?.quantity ?? 0);
  const isOutOfStock = (p: Product) => p.track_inventory && !p.is_service && getStock(p) <= 0;

  const loadSalesHistory = async () => {
    setHistoryLoading(true);

    try {
      const branchId =
        localStorage.getItem('mystocks_branch_id') ||
        currentBusiness?.branch_id ||
        balances[0]?.branch_id ||
        undefined;

      const result = await fetchSales({
        branch_id: branchId,
        per_page: 50,
      });

      setSalesHistory(result.data);
    } catch {
      setMessage({ type: 'error', text: 'Could not load sales history.' });
    } finally {
      setHistoryLoading(false);
    }
  };

  const openSaleFromHistory = async (id: string) => {
    try {
      const sale = await fetchSale(id);
      setLastSale(sale);
    } catch {
      setMessage({ type: 'error', text: 'Could not load sale details.' });
    }
  };
  const handleCancelSale = async (sale: Sale) => {
    if (!canCancelSales || sale.status !== 'completed') return;
    if (!confirm('Cancel sale ' + sale.sale_number + '? Stock will be restored.')) return;
    try {
      await cancelSale(sale.id);
      setMessage({ type: 'success', text: 'Sale cancelled and stock restored.' });
      setLastSale(null);
      await loadSalesHistory();
      await loadProducts();
    } catch (err: any) {
      setMessage({ type: 'error', text: err?.response?.data?.message || 'Could not cancel sale.' });
    }
  };

  const completeSale = async () => {
    if (cart.length === 0) return;
    if (paymentMethod === 'credit' && !selectedCustomerId) {
      setMessage({ type: 'error', text: 'Select a customer before saving a credit sale.' });
      return;
    }
    setSubmitting(true);
    setMessage(null);

    const branchId = localStorage.getItem('mystocks_branch_id') || currentBusiness?.branch_id || balances[0]?.branch_id || undefined;
    const deviceId = localStorage.getItem('mystocks_device_id') || uuidv4();
    localStorage.setItem('mystocks_device_id', deviceId);

    const payload: CreateSalePayload = {
      branch_id: branchId,
      items: cart.map((i) => ({
        product_id: i.product.id,
        quantity: i.quantity,
        unit_selling_price: i.unit_selling_price,
        discount_amount: i.discount_amount,
      })),
      payment:
        paymentMethod === 'credit'
          ? { method: 'credit' }
          : {
              method: paymentMethod,
              amount: subtotal,
            },
      idempotency_key: uuidv4(),
      device_id: deviceId,
      customer_id: paymentMethod === 'credit' ? selectedCustomerId : undefined,
    };

    try {
      if (!isOnline) {
        enqueue('sale', payload as unknown as Record<string, unknown>);
        setMessage({
          type: 'success',
          text: 'Sale saved offline. It will sync when you are back online.',
        });
        setCart([]);
        setSelectedCustomerId('');
        setShowCart(false);
        return;
      }

      const sale = await createSale(payload);
      setLastSale(sale);
      setMessage({ type: 'success', text: `Sale completed · ${sale.sale_number} · GHS ${money(subtotal)}` });
      setCart([]);
      setSelectedCustomerId('');
      setShowCart(false);
      // Opportunistic sync of any pending offline ops
      syncAll();
    } catch (err: unknown) {
      const apiMsg =
        (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
          ?.response?.data?.message ||
        (err as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data
          ?.errors &&
          Object.values(
            (err as { response: { data: { errors: Record<string, string[]> } } }).response.data.errors
          )
            .flat()
            .join(' ');

      // If network error, queue offline
      if (!(err as { response?: unknown })?.response) {
        enqueue('sale', payload as unknown as Record<string, unknown>);
        setMessage({
          type: 'success',
          text: 'Network issue — sale saved offline and will sync later.',
        });
        setCart([]);
        setSelectedCustomerId('');
        setShowCart(false);
      } else {
        setMessage({
          type: 'error',
          text: typeof apiMsg === 'string' ? apiMsg : 'Sale failed. Check stock and try again.',
        });
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="relative flex flex-col gap-4 lg:flex-row">
      {/* Product grid */}
      <div className="flex-1 space-y-4">
        <div className="flex items-center justify-between gap-3">
          <h1 className="text-xl font-bold text-slate-900">Sales</h1>
        <div className="flex rounded-xl bg-slate-100 p-1">
          <button type="button" onClick={() => setView('pos')} className={`flex-1 rounded-lg px-3 py-2 text-sm font-semibold ${view==='pos'?'bg-white text-teal-700 shadow-sm':'text-slate-500'}`}>New Sale</button>
          <button type="button" onClick={() => { setView('history'); loadSalesHistory(); }} className={`flex-1 rounded-lg px-3 py-2 text-sm font-semibold ${view==='history'?'bg-white text-teal-700 shadow-sm':'text-slate-500'}`}>History</button>
        </div>
          <button
            onClick={() => setShowCart(true)}
            className={`${view === 'history' ? 'hidden ' : ''}relative flex items-center gap-2 rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white lg:hidden`}
          >
            <ShoppingCart className="h-4 w-4" />
            Cart
            {cartCount > 0 && (
              <span className="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-white">
                {cartCount}
              </span>
            )}
          </button>
        </div>

        <div className={view === 'pos' ? 'relative' : 'hidden'}>
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            type="search"
            placeholder="Search products, SKU, barcode…"
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

        {view === 'history' && <div className="space-y-3">{historyLoading ? <div className="py-12 text-center text-slate-500">Loading sales history…</div> : salesHistory.length === 0 ? <div className="rounded-xl bg-white py-12 text-center text-slate-500 shadow-sm">No sales recorded yet.</div> : <div className="overflow-hidden rounded-xl bg-white shadow-sm"><div className="divide-y divide-slate-100">{salesHistory.map(sale => <div key={sale.id} className="flex items-center gap-3 p-4"><div className="min-w-0 flex-1"><div className="font-semibold text-slate-900">{sale.sale_number}</div><div className="mt-1 text-xs text-slate-500">{sale.sold_at ? new Date(sale.sold_at).toLocaleString('en-GB') : '—'}</div><div className={`mt-1 text-xs capitalize ${sale.status === 'cancelled' ? 'font-bold text-red-600' : 'text-slate-500'}`}>{sale.cashier?.name || '—'} · {sale.status === 'cancelled' ? 'Cancelled' : sale.payment_status} · {sale.item_count ?? 0} product line{sale.item_count === 1 ? '' : 's'}</div></div><div className="text-right"><div className={`font-bold ${sale.status === 'cancelled' ? 'text-slate-400 line-through' : ''}`}>GHS {money(Number(sale.total))}</div><button type="button" onClick={() => openSaleFromHistory(sale.id)} className="mt-2 rounded-lg bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-700">View receipt</button></div></div>)}</div></div>}</div>}
        {view === 'pos' && (loading ? (
          <div className="py-16 text-center text-slate-500">Loading products…</div>
        ) : products.length === 0 ? (
          <div className="rounded-xl bg-white py-16 text-center text-slate-500 shadow-sm">
            No products found. Add products first.
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            {products.map((p) => (
              <button
                key={p.id}
                onClick={() => !isOutOfStock(p) && addToCart(p)}
                disabled={isOutOfStock(p)}
                className="flex flex-col rounded-xl border border-slate-200 bg-white p-3 text-left shadow-sm transition active:scale-[0.98] hover:border-teal-300 hover:shadow"
              >
                <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-teal-50 text-sm font-bold text-teal-700">
                  {p.name.slice(0, 2).toUpperCase()}
                </div>
                <div className="line-clamp-2 text-sm font-medium text-slate-900">{p.name}</div>
                <div className="mt-1 text-xs text-slate-400">{p.unit}</div>
                {p.track_inventory && !p.is_service && <div className={`mt-1 text-xs font-medium ${isOutOfStock(p) ? "text-red-600" : getStock(p) <= Number(p.minimum_stock_level || 0) ? "text-amber-600" : "text-slate-500"}`}>{isOutOfStock(p) ? "Out of stock" : `Stock: ${getStock(p).toLocaleString("en-GH",{maximumFractionDigits:2})}`}</div>}
                <div className="mt-2 text-base font-bold tabular-nums text-teal-800">
                  GHS {money(parseFloat(p.selling_price) || 0)}
                </div>
              </button>
            ))}
          </div>
        ))}
      </div>

      {/* Cart panel - desktop always visible, mobile as drawer */}
      <div
        className={`
          ${view === 'history' ? 'hidden ' : ''}fixed inset-y-0 right-0 z-30 flex w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-xl transition-transform lg:static lg:z-0 lg:max-w-sm lg:translate-x-0 lg:shadow-sm lg:rounded-xl lg:border
          ${showCart ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'}
        `}
      >
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h2 className="font-semibold text-slate-900">Current sale</h2>
          <button onClick={() => setShowCart(false)} className="rounded-lg p-1.5 text-slate-500 lg:hidden">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-4">
          {cart.length === 0 ? (
            <div className="flex h-40 flex-col items-center justify-center text-slate-400">
              <ShoppingCart className="mb-2 h-8 w-8" />
              <p className="text-sm">Tap products to add</p>
            </div>
          ) : (
            <ul className="space-y-3">
              {cart.map((item) => (
                <li
                  key={item.product.id}
                  className="flex items-start gap-3 rounded-lg border border-slate-100 p-3"
                >
                  <div className="flex-1">
                    <div className="text-sm font-medium text-slate-900">{item.product.name}</div>
                    <div className="text-xs text-slate-500">
                      GHS {money(item.unit_selling_price)} each
                    </div>
                    <div className="mt-2 flex items-center gap-2">
                      <button
                        onClick={() => updateQty(item.product.id, -1)}
                        className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600"
                      >
                        <Minus className="h-4 w-4" />
                      </button>
                      <span className="w-8 text-center text-sm font-semibold tabular-nums">
                        {item.quantity}
                      </span>
                      <button
                        onClick={() => updateQty(item.product.id, 1)}
                        className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600"
                      >
                        <Plus className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                  <div className="text-right">
                    <div className="text-sm font-bold tabular-nums text-slate-900">
                      GHS {money(item.quantity * item.unit_selling_price)}
                    </div>
                    <button
                      onClick={() => removeItem(item.product.id)}
                      className="mt-2 text-slate-400 hover:text-red-500"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="border-t border-slate-100 p-4 pb-24 space-y-3 lg:pb-4">
          <div className="flex justify-between text-sm">
            <span className="text-slate-500">Subtotal</span>
            <span className="font-bold tabular-nums text-slate-900">GHS {money(subtotal)}</span>
          </div>

          <div className="grid grid-cols-4 gap-1.5">
            {(['cash', 'mobile_money', 'card', 'credit'] as const).map((m) => (
              <button
                key={m}
                onClick={() => setPaymentMethod(m)}
                className={`rounded-lg py-2 text-[11px] font-medium capitalize ${
                  paymentMethod === m
                    ? 'bg-teal-700 text-white'
                    : 'bg-slate-100 text-slate-600'
                }`}
              >
                {m === 'mobile_money' ? 'MoMo' : m}
              </button>
            ))}
          </div>

          {paymentMethod === 'credit' && (
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-600">Customer *</label>
              <select
                value={selectedCustomerId}
                onChange={(e) => setSelectedCustomerId(e.target.value)}
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-teal-600"
              >
                <option value="">Select customer</option>
                {customers.filter((c) => c.status === 'active').map((c) => (
                  <option key={c.id} value={c.id}>{c.name} · owes GHS {money(Number(c.outstanding_balance || 0))}</option>
                ))}
              </select>
              {customers.length === 0 && <p className="mt-1 text-xs text-amber-700">Add an active customer before making a credit sale.</p>}
            </div>
          )}

          <button
            onClick={completeSale}
            disabled={cart.length === 0 || submitting || (paymentMethod === 'credit' && !selectedCustomerId)}
            className="w-full rounded-xl bg-teal-700 py-3.5 text-sm font-bold text-white transition hover:bg-teal-800 disabled:opacity-50"
          >
            {submitting
              ? 'Processing…'
              : isOnline
                ? `Charge GHS ${money(subtotal)}`
                : `Save offline · GHS ${money(subtotal)}`}
          </button>
        </div>
      </div>

      <style>{`@media print { body > *:not(.receipt-print-portal) { display: none !important; } .receipt-print-portal { position: static !important; inset: auto !important; overflow: visible !important; background: white !important; padding: 0 !important; } .receipt-print { display: block !important; position: static !important; width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border-radius: 0 !important; background: white !important; break-inside: avoid !important; } .receipt-actions { display: none !important; } @page { size: auto; margin: 8mm; } }`}</style>
      {lastSale && createPortal((
        <div className="receipt-print-portal fixed inset-0 z-50 overflow-y-auto bg-black/40 p-4 pb-24 sm:flex sm:items-center sm:justify-center sm:pb-4">
          <div className="receipt-print mx-auto w-full max-w-md rounded-2xl bg-white p-5 pb-8 shadow-xl sm:my-auto">
            <div className="text-center">
              <div className="text-lg font-bold text-slate-900">{currentBusiness?.name || 'CNMG STOCKS'}</div>
              <div className="mt-1 text-sm font-semibold uppercase tracking-wide text-slate-600">Transaction receipt</div>
              <div className="mt-1 text-sm font-medium text-teal-700">
                {lastSale.sale_number}
              </div>
            </div>

            <div className="mt-5 space-y-2 rounded-xl bg-slate-50 p-4 text-sm">
              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Status</span>
                <span className={`font-bold uppercase ${receiptCancelled ? 'text-red-600' : 'text-emerald-700'}`}>
                  {receiptCancelled ? 'Cancelled' : 'Completed'}
                </span>
              </div>
              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Date</span>
                <span className="text-right font-medium">
                  {lastSale.sold_at
                    ? new Date(lastSale.sold_at).toLocaleString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })
                    : new Date().toLocaleString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })}
                </span>
              </div>

              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Cashier</span>
                <span className="font-medium">
                  {lastSale.cashier?.name || '—'}
                </span>
              </div>

              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Branch</span>
                <span className="font-medium">
                  {lastSale.branch?.name || '—'}
                </span>
              </div>

              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Payment</span>
                <span className="font-semibold capitalize">
                  {lastSale.payments?.[0]?.method?.replace('_', ' ') || lastSale.payment_status}
                </span>
              </div>

              {lastSale.customer && (
                <div className="flex justify-between gap-3">
                  <span className="text-slate-500">Customer</span>
                  <span className="text-right font-medium">
                    {lastSale.customer.name}{lastSale.customer.phone ? ` · ${lastSale.customer.phone}` : ''}
                  </span>
                </div>
              )}

              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Items</span>
                <span className="font-medium">{receiptItemCount}</span>
              </div>
            </div>

            <div className="mt-4 divide-y divide-slate-100">
              {(lastSale.items || []).map((item) => (
                <div
                  key={item.id}
                  className="flex items-start justify-between gap-3 py-3"
                >
                  <div>
                    <div className="font-medium text-slate-900">
                      {item.product_name}
                    </div>
                    <div className="text-xs text-slate-500">
                      {Number(item.quantity).toLocaleString('en-GH', {
                        maximumFractionDigits: 2,
                      })}
                      {' × '}
                      GHS {money(Number(item.unit_selling_price))}
                    </div>
                  </div>

                  <div className="font-semibold">
                    GHS {money(Number(item.line_total))}
                  </div>
                </div>
              ))}
            </div>

            <div className="mt-4 space-y-2 border-t pt-4 text-sm">
              <div className="flex items-center justify-between text-slate-600">
                <span>Subtotal</span>
                <span>GHS {money(Number(lastSale.subtotal))}</span>
              </div>
              {Number(lastSale.discount_amount) > 0 && <div className="flex items-center justify-between text-slate-600"><span>Discount</span><span>- GHS {money(Number(lastSale.discount_amount))}</span></div>}
              {Number(lastSale.tax_amount) > 0 && <div className="flex items-center justify-between text-slate-600"><span>Tax</span><span>GHS {money(Number(lastSale.tax_amount))}</span></div>}
              <div className="flex items-center justify-between border-t pt-2">
                <span className="font-semibold text-slate-700">Grand total</span>
                <span className="text-xl font-bold text-teal-800">
                  GHS {money(Number(lastSale.total))}
                </span>
              </div>
              {!receiptCancelled && lastSale.payment_status !== 'credit' && <div className="flex items-center justify-between text-slate-600"><span>Amount paid</span><span>GHS {money(receiptAmountPaid)}</span></div>}
              {!receiptCancelled && receiptBalance > 0 && <div className="flex items-center justify-between font-semibold text-amber-700"><span>Balance due</span><span>GHS {money(receiptBalance)}</span></div>}
              {!receiptCancelled && receiptChange > 0 && <div className="flex items-center justify-between text-slate-600"><span>Change</span><span>GHS {money(receiptChange)}</span></div>}
              {receiptCancelled && <div className="rounded-lg bg-red-50 px-3 py-2 text-center font-bold text-red-700">CANCELLED — NO AMOUNT DUE</div>}
            </div>

            <div className="receipt-actions mt-5 grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setLastSale(null)}
                className="rounded-lg border py-2.5 font-semibold text-slate-700"
              >
                New Sale
              </button>

              <button
                type="button"
                onClick={() => window.print()}
                className="rounded-lg bg-teal-700 py-2.5 font-semibold text-white"
              >
                Print Receipt
              </button>
              <a
                href={receiptWhatsAppUrl}
                target="_blank"
                rel="noreferrer"
                className="col-span-2 flex items-center justify-center gap-2 rounded-lg bg-emerald-600 py-2.5 font-semibold text-white"
              >
                <MessageCircle className="h-5 w-5" />
                Share receipt on WhatsApp
              </a>
              {canCancelSales && lastSale.status === 'completed' && <button type="button" onClick={() => handleCancelSale(lastSale)} className="col-span-2 rounded-lg bg-red-600 py-2.5 font-semibold text-white sm:col-span-1">Cancel Sale</button>}
            </div>
          </div>
        </div>
      ), document.body)}

      {/* Mobile cart backdrop */}
      {showCart && (
        <div
          className="fixed inset-0 z-20 bg-black/30 lg:hidden"
          onClick={() => setShowCart(false)}
        />
      )}
    </div>
  );
}
