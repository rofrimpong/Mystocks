import { useEffect } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuthStore } from './stores/authStore';
import AppLayout from './layouts/AppLayout';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import DashboardPage from './pages/DashboardPage';
import ProductsPage from './pages/ProductsPage';
import PlaceholderPage from './pages/PlaceholderPage';

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading, loadFromStorage } = useAuthStore();

  useEffect(() => {
    loadFromStorage();
  }, [loadFromStorage]);

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center text-slate-500">
        Loading…
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />

      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<DashboardPage />} />
        <Route path="sales" element={<PlaceholderPage title="Sales" />} />
        <Route path="products" element={<ProductsPage />} />
        <Route path="inventory" element={<PlaceholderPage title="Inventory" />} />
        <Route path="customers" element={<PlaceholderPage title="Customers" />} />
        <Route path="suppliers" element={<PlaceholderPage title="Suppliers" />} />
        <Route path="expenses" element={<PlaceholderPage title="Expenses" />} />
        <Route path="reports" element={<PlaceholderPage title="Reports" />} />
        <Route path="settings" element={<PlaceholderPage title="Settings" />} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
