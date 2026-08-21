import api from './api';
import type { AdminPlan } from './admin';

export async function fetchPlans(): Promise<AdminPlan[]> {
  const { data } = await api.get<{ data: AdminPlan[] }>('/plans');
  return data.data;
}
