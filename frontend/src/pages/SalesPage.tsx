import { useCallback, useEffect, useMemo, useState } from 'react';
import { Minus, Plus, Search, ShoppingCart, Trash2, X } from 'lucide-react';
import { fetchProducts, createSale, type CartItem, type CreateSalePayload } from '../services/sales';
import type { Product } from '../types';
import { useOfflineStore } from '../stores/offlineStore';
import { useAuthStore } from '../stores/authStore';
import { fetchBalances, type InventoryBalance } from '../services/inventory';
import { v4 as uuidv4 } from 'uuid';

function money(n: number) {
  return n.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

  const { isOnline, enqueue, syncAll } = useOfflineStore();
  const { businesses, currentBusinessId } = useAuthStore();
  const currentBusiness = businesses.find((b) => b.id === currentBusinessId) || businesses[0];

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
  const getStock = (p: Product) => Number(balances.find((b) => b.product_id === p.id)?.available_quantity ?? balances.find((b) => b.product_id === p.id)?.quantity ?? 0);
  const isOutOfStock = (p: Product) => p.track_inventory && !p.is_service && getStock(p) <= 0;

  const completeSale = async () => {
    if (cart.length === 0) return;
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
    };

    try {
      if (!isOnline) {
        enqueue('sale', payload as unknown as Record<string, unknown>);
        setMessage({
          type: 'success',
          text: 'Sale saved offline. It will sync when you are back online.',
        });
        setCart([]);
        setShowCart(false);
        return;
      }

      await createSale(payload);
      setMessage({ type: 'success', text: `Sale completed — GHS ${money(subtotal)}` });
      setCart([]);
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
          <button
            onClick={() => setShowCart(true)}
            className="relative flex items-center gap-2 rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white lg:hidden"
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

        <div className="relative">
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

        {loading ? (
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
        )}
      </div>

      {/* Cart panel - desktop always visible, mobile as drawer */}
      <div
        className={`
          fixed inset-y-0 right-0 z-30 flex w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-xl transition-transform lg:static lg:z-0 lg:max-w-sm lg:translate-x-0 lg:shadow-sm lg:rounded-xl lg:border
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

          <button
            onClick={completeSale}
            disabled={cart.length === 0 || submitting}
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
