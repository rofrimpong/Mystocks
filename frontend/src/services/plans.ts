import api from './api';
import type { AdminPlan } from './admin';

export async function fetchPlans(): Promise<AdminPlan[]> {
  const { data } = await api.get<{ data: AdminPlan[] }>('/plans');
  return data.data;
}

export async function initializeBilling(planSlug: string, billingCycle: 'monthly' | 'yearly') {
  const { data } = await api.post('/billing/initialize', {
    plan_slug: planSlug,
    billing_cycle: billingCycle,
  });

  return data.data as {
    authorization_url: string;
    reference: string;
  };
}

export async function verifyBilling(reference: string) {
  const { data } = await api.post('/billing/verify', { reference });

  return data as {
    message: string;
    data?: {
      plan: string;
      billing_cycle: 'monthly' | 'yearly';
      current_period_ends_at: string;
    };
  };
}
