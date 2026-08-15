import api from './api';

export interface Supplier {
  id: string;
  name: string;
  company?: string | null;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  notes?: string | null;
  outstanding_balance: string;
  status: string;
}

export async function fetchSuppliers(params?: {
  search?: string;
  active_only?: boolean;
  with_balance?: boolean;
  per_page?: number;
}): Promise<{ data: Supplier[]; meta: { total: number } }> {
  const { data } = await api.get('/suppliers', { params });
  return data;
}

export async function createSupplier(payload: {
  name: string;
  company?: string;
  phone?: string;
  email?: string;
  address?: string;
  notes?: string;
  status?: string;
}) {
  const { data } = await api.post('/suppliers', payload);
  return data;
}
