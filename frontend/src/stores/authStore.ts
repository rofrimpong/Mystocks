import { create } from 'zustand';
import type { User, Business } from '../types';
import * as authService from '../services/auth';

interface AuthState {
  user: User | null;
  businesses: Business[];
  currentBusinessId: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  setSession: (user: User, token: string, businesses?: Business[], businessId?: string) => void;
  loadFromStorage: () => void;
  logout: () => Promise<void>;
  setCurrentBusiness: (businessId: string) => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  businesses: [],
  currentBusinessId: localStorage.getItem('mystocks_business_id'),
  isLoading: true,
  isAuthenticated: false,

  setSession: (user, token, businesses = [], businessId) => {
    authService.saveSession(token, user, businessId);
    set({
      user,
      businesses,
      currentBusinessId: businessId || businesses[0]?.id || null,
      isAuthenticated: true,
      isLoading: false,
    });
    if (businessId || businesses[0]?.id) {
      localStorage.setItem('mystocks_business_id', businessId || businesses[0].id);
    }
  },

  loadFromStorage: () => {
    const user = authService.getStoredUser();
    const token = localStorage.getItem('mystocks_token');
    const businessId = localStorage.getItem('mystocks_business_id');
    if (user && token) {
      set({
        user,
        currentBusinessId: businessId,
        isAuthenticated: true,
        isLoading: false,
      });
    } else {
      set({ isLoading: false, isAuthenticated: false });
    }
  },

  logout: async () => {
    await authService.logout();
    set({
      user: null,
      businesses: [],
      currentBusinessId: null,
      isAuthenticated: false,
    });
  },

  setCurrentBusiness: (businessId: string) => {
    localStorage.setItem('mystocks_business_id', businessId);
    set({ currentBusinessId: businessId });
  },
}));
