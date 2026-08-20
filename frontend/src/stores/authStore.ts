import { create } from 'zustand';
import type { User, Business } from '../types';
import * as authService from '../services/auth';

interface AuthState {
  user: User | null;
  businesses: Business[];
  currentBusinessId: string | null;
  currentBusinessRole: Business['role'] | null;
  isBusinessOwner: boolean;
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
  currentBusinessRole: null,
  isBusinessOwner: false,
  isLoading: true,
  isAuthenticated: false,

  setSession: (user, token, businesses = [], businessId) => {
    authService.saveSession(token, user, businessId);
    const selectedBusiness = businesses.find((b) => b.id === (businessId || businesses[0]?.id)) || businesses[0];
    set({
      user,
      businesses,
      currentBusinessId: businessId || businesses[0]?.id || null,
      currentBusinessRole: selectedBusiness?.role || null,
      isBusinessOwner: !!selectedBusiness?.is_owner,
      isAuthenticated: true,
      isLoading: false,
    });
    localStorage.setItem('mystocks_businesses', JSON.stringify(businesses));

    if (businessId || businesses[0]?.id) {
      localStorage.setItem('mystocks_business_id', businessId || businesses[0].id);
    }
  },

  loadFromStorage: () => {
    const user = authService.getStoredUser();
    const token = localStorage.getItem('mystocks_token');
    const businessId = localStorage.getItem('mystocks_business_id');
    const storedBusinesses = localStorage.getItem('mystocks_businesses');

    let businesses: Business[] = [];

    if (storedBusinesses) {
      try {
        businesses = JSON.parse(storedBusinesses) as Business[];
      } catch {
        businesses = [];
      }
    }

    const selectedBusiness =
      businesses.find((business) => business.id === businessId) ||
      businesses[0];

    if (user && token) {
      set({
        user,
        businesses,
        currentBusinessId: businessId,
        currentBusinessRole: selectedBusiness?.role || null,
        isBusinessOwner: !!selectedBusiness?.is_owner,
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
      currentBusinessRole: null,
      isBusinessOwner: false,
      isAuthenticated: false,
    });
  },

  setCurrentBusiness: (businessId: string) => {
    localStorage.setItem('mystocks_business_id', businessId);

    set((state) => {
      const selectedBusiness = state.businesses.find(
        (business) => business.id === businessId
      );

      return {
        currentBusinessId: businessId,
        currentBusinessRole: selectedBusiness?.role || null,
        isBusinessOwner: !!selectedBusiness?.is_owner,
      };
    });
  },
}));
