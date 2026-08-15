import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 30000,
});

// Attach token + business context
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem('mystocks_token');
  const businessId = localStorage.getItem('mystocks_business_id');
  const branchId = localStorage.getItem('mystocks_branch_id');

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  if (businessId) {
    config.headers['X-Business-Id'] = businessId;
  }
  if (branchId) {
    config.headers['X-Branch-Id'] = branchId;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('mystocks_token');
      localStorage.removeItem('mystocks_user');
      // Let the app handle redirect
      window.dispatchEvent(new CustomEvent('mystocks:unauthorized'));
    }
    return Promise.reject(error);
  }
);

export default api;
