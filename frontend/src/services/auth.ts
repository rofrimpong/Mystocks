import api from './api';
import type { AuthResponse, User, Business } from '../types';

export async function login(email: string, password: string, deviceName = 'web') {
  const { data } = await api.post<AuthResponse>('/auth/login', {
    email,
    password,
    device_name: deviceName,
  });
  return data;
}

export async function register(payload: {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  business_name: string;
  phone?: string;
  referral_code?: string;
  device_name?: string;
}) {
  const { data } = await api.post<AuthResponse>('/auth/register', payload);
  return data;
}

export async function logout() {
  try {
    await api.post('/auth/logout');
  } finally {
    localStorage.removeItem('mystocks_token');
    localStorage.removeItem('mystocks_user');
    localStorage.removeItem('mystocks_business_id');
    localStorage.removeItem('mystocks_branch_id');
  }
}

export async function me() {
  const { data } = await api.get<{ data: { user: User; businesses: Business[] } }>('/auth/me');
  return data.data;
}

export function saveSession(token: string, user: User, businessId?: string, branchId?: string) {
  localStorage.setItem('mystocks_token', token);
  localStorage.setItem('mystocks_user', JSON.stringify(user));
  if (businessId) localStorage.setItem('mystocks_business_id', businessId);
  if (branchId) localStorage.setItem('mystocks_branch_id', branchId);
}

export function getStoredUser(): User | null {
  const raw = localStorage.getItem('mystocks_user');
  if (!raw) return null;
  try {
    return JSON.parse(raw) as User;
  } catch {
    return null;
  }
}

export function isAuthenticated(): boolean {
  return !!localStorage.getItem('mystocks_token');
}
