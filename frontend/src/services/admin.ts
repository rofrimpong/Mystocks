import api from './api';
import type { User } from '../types';

export interface AdminPlan {
  id: string;
  name: string;
  slug: 'free' | 'starter' | 'business' | 'pro' | 'enterprise';
  description?: string | null;
  price_monthly: string | number;
  price_yearly: string | number;
  currency: string;
  max_products?: number | null;
  max_users?: number | null;
  max_branches?: number | null;
  has_reports: boolean;
  has_multi_branch: boolean;
  has_api_access: boolean;
  has_priority_support: boolean;
  is_active: boolean;
  sort_order: number;
}

export async function getAdminSummary() {
  const { data } = await api.get('/admin/summary');
  return data.data;
}

export async function getAdminUsers(search = '') {
  const { data } = await api.get('/admin/users', {
    params: { search, per_page: 50 },
  });
  return data.data as User[];
}

export async function updateAdminUser(
  id: string,
  payload: { is_active?: boolean; is_platform_admin?: boolean }
) {
  const { data } = await api.patch(`/admin/users/${id}`, payload);
  return data.data as User;
}

export async function getAdminBusinesses() {
  const { data } = await api.get('/admin/businesses', {
    params: { per_page: 50 },
  });

  return data.data as Array<{
    id: string;
    name: string;
    status: string;
    plan?: string;
    users_count: number;
  }>;
}

export async function updateAdminBusiness(
  id: string,
  payload: { status?: string; plan?: string }
) {
  const { data } = await api.patch(`/admin/businesses/${id}`, payload);
  return data.data;
}

export async function getAdminPlans(): Promise<AdminPlan[]> {
  const { data } = await api.get<{ data: AdminPlan[] }>('/admin/plans');
  return data.data;
}

export async function updateAdminPlan(
  id: string,
  payload: Partial<AdminPlan>
): Promise<AdminPlan> {
  const { data } = await api.patch<{ data: AdminPlan }>(
    `/admin/plans/${id}`,
    payload
  );

  return data.data;
}
