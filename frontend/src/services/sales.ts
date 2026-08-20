import api from './api';
import type { Product, Sale } from '../types';

export interface CartItem {
  product: Product;
  quantity: number;
  unit_selling_price: number;
  discount_amount: number;
}

export interface CreateSalePayload {
  branch_id?: string;
  customer_id?: string;
  notes?: string;
  discount_amount?: number;
  items: Array<{
    product_id: string;
    quantity: number;
    unit_selling_price?: number;
    discount_amount?: number;
  }>;
  payment?: {
    method: 'cash' | 'mobile_money' | 'card' | 'bank_transfer' | 'credit' | 'other';
    amount?: number;
    reference?: string;
    provider?: string;
  };
  idempotency_key?: string;
  device_id?: string;
}

export async function fetchProducts(search?: string): Promise<Product[]> {
  const { data } = await api.get<{ data: Product[] }>('/products', {
    params: { active_only: true, search: search || undefined, per_page: 50 },
  });
  return data.data;
}

export async function createSale(payload: CreateSalePayload): Promise<Sale> {
  const { data } = await api.post<{ data: Sale }>('/sales', payload);
  return data.data;
}

export async function fetchSales(params?: {
  branch_id?: string;
  payment_status?: string;
  cashier_id?: string;
  from?: string;
  to?: string;
  per_page?: number;
}): Promise<{ data: Sale[]; meta: { total: number } }> {
  const { data } = await api.get('/sales', { params });
  return data;
}

export async function fetchSale(id: string): Promise<Sale> {
  const { data } = await api.get<{ data: Sale }>(`/sales/${id}`);
  return data.data;
}
