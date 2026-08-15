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
