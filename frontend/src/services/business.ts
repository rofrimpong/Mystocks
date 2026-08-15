import api from './api';
import type { Business, Branch } from '../types';

export async function fetchBusinesses(): Promise<Business[]> {
  const { data } = await api.get<{ data: Business[] }>('/businesses');
  return data.data;
}

export async function fetchCurrentBusiness(): Promise<{
  data: Business;
  meta?: { is_owner?: boolean; current_branch_id?: string };
}> {
  const { data } = await api.get('/business/current');
  return data;
}

export async function updateBusiness(
  id: string,
  payload: Partial<{
    name: string;
    phone: string;
    email: string;
    address: string;
    city: string;
    region: string;
    currency: string;
    timezone: string;
  }>
) {
  const { data } = await api.put(`/businesses/${id}`, payload);
  return data;
}

export async function fetchBranches(): Promise<Branch[]> {
  const { data } = await api.get<{ data: Branch[] }>('/branches');
  return data.data;
}
