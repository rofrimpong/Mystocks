import api from './api';

export interface Customer {
  id: string;
  name: string;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  notes?: string | null;
  credit_limit: string;
  outstanding_balance: string;
  status: string;
}

export interface CustomerTransaction {
  id: string;
  type: 'sale' | 'payment' | 'credit_note' | 'opening_balance' | 'adjustment';
  amount: string;
  balance_after: string;
  payment_method?: string | null;
  payment_reference?: string | null;
  reference_type?: string | null;
  reference_id?: string | null;
  notes?: string | null;
  occurred_at: string;
  created_by?: { id: string; name: string } | null;
}

export async function fetchCustomers(params?: {
  search?: string;
  active_only?: boolean;
  with_balance?: boolean;
  per_page?: number;
}): Promise<{ data: Customer[]; meta: { total: number } }> {
  const { data } = await api.get('/customers', { params });
  return data;
}

export async function createCustomer(payload: {
  name: string;
  phone?: string;
  email?: string;
  address?: string;
  notes?: string;
  credit_limit?: number;
  status?: string;
}) {
  const { data } = await api.post('/customers', payload);
  return data;
}

export async function updateCustomer(
  id: string,
  payload: Partial<{
    name: string;
    phone: string;
    email: string;
    address: string;
    notes: string;
    credit_limit: number;
    status: string;
  }>
) {
  const { data } = await api.put(`/customers/${id}`, payload);
  return data;
}

export async function fetchCustomerTransactions(id: string): Promise<{ data: CustomerTransaction[] }> {
  const { data } = await api.get(`/customers/${id}/transactions`);
  return data;
}

export async function recordCustomerPayment(id: string, payload: {
  amount: number;
  payment_method: 'cash' | 'mobile_money' | 'card' | 'bank_transfer';
  reference?: string;
  notes?: string;
}): Promise<Customer> {
  const { data } = await api.post(`/customers/${id}/payments`, payload);
  return data.data?.customer || data.data || data;
}

export async function recordOpeningBalance(id: string, payload: { amount: number; notes?: string }): Promise<Customer> {
  const { data } = await api.post(`/customers/${id}/opening-balance`, payload);
  return data.data?.customer || data.data || data;
}

export async function recordCustomerAdjustment(id: string, payload: { direction: 'increase' | 'decrease'; amount: number; notes: string }): Promise<Customer> {
  const { data } = await api.post(`/customers/${id}/adjustments`, payload);
  return data.data?.customer || data.data || data;
}
