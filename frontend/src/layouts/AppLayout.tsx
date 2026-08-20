import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import {
  LayoutDashboard,
  ShoppingCart,
  Package,
  Warehouse,
  Users,
  Truck,
  Receipt,
  BarChart3,
  Settings,
  UserCircle,
  ShieldCheck,
  LogOut,
  Wifi,
  WifiOff,
  RefreshCw,
} from 'lucide-react';
import { useAuthStore } from '../stores/authStore';
import { useOfflineStore } from '../stores/offlineStore';
import { useEffect } from 'react';

const baseNavItems = [
  { to: '/', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/sales', icon: ShoppingCart, label: 'Sales' },
  { to: '/products', icon: Package, label: 'Products' },
  { to: '/inventory', icon: Warehouse, label: 'Inventory' },
  { to: '/customers', icon: Users, label: 'Customers' },
  { to: '/suppliers', icon: Truck, label: 'Suppliers' },
  { to: '/expenses', icon: Receipt, label: 'Expenses' },
  { to: '/reports', icon: BarChart3, label: 'Reports' },
  { to: '/settings', icon: Settings, label: 'Settings' },
];

export default function AppLayout() {
  const { user, logout } = useAuthStore();
  const navItems = user?.is_platform_admin ? [...baseNavItems, { to: '/admin', icon: ShieldCheck, label: 'Admin' }] : baseNavItems;
  const { isOnline, isSyncing, pendingCount, setOnline, syncAll } = useOfflineStore();
  const navigate = useNavigate();
  const pending = pendingCount();

  useEffect(() => {
    const onOnline = () => {
      setOnline(true);
      syncAll();
    };
    const onOffline = () => setOnline(false);
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    return () => {
      window.removeEventListener('online', onOnline);
      window.removeEventListener('offline', onOffline);
    };
  }, [setOnline, syncAll]);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <div className="flex min-h-screen flex-col bg-slate-50 md:flex-row">
      {/* Sidebar - desktop */}
      <aside className="hidden w-64 flex-shrink-0 flex-col border-r border-slate-200 bg-white md:flex">
        <div className="flex h-16 items-center gap-2 border-b border-slate-100 px-5">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-700 text-sm font-bold text-white">
            CS
          </div>
          <div>
            <div className="text-sm font-semibold text-slate-900">CNMG STOCKS</div>
            <div className="text-xs text-slate-500">CNMG Technologies</div>
          </div>
        </div>

        <nav className="flex-1 space-y-0.5 p-3">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === '/'}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition ${
                  isActive
                    ? 'bg-teal-50 text-teal-800'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                }`
              }
            >
              <item.icon className="h-5 w-5" />
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-slate-100 p-3">
          <div className="mb-2 truncate px-3 text-xs text-slate-500">{user?.name}</div>
          <button
            onClick={handleLogout}
            className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
          >
            <LogOut className="h-5 w-5" />
            Logout
          </button>
        </div>
      </aside>

      {/* Main */}
      <div className="flex flex-1 flex-col">
        {/* Top bar */}
        <header className="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4">
          <div className="flex items-center gap-2 md:hidden">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-700 text-xs font-bold text-white">
              CS
            </div>
            <span className="font-semibold text-slate-900">CNMG STOCKS</span>
          </div>

          <div className="flex items-center gap-3">
            <button onClick={() => navigate('/profile')} className="flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700" title="Profile">
              <UserCircle className="h-4 w-4" />
              <span className="hidden sm:inline">Profile</span>
            </button>
            {/* Online / Offline indicator */}
            <div
              className={`flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${
                isOnline ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'
              }`}
            >
              {isOnline ? <Wifi className="h-3.5 w-3.5" /> : <WifiOff className="h-3.5 w-3.5" />}
              {isOnline ? 'Online' : 'Offline'}
            </div>

            {pending > 0 && (
              <button
                onClick={() => syncAll()}
                disabled={!isOnline || isSyncing}
                className="flex items-center gap-1.5 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700 disabled:opacity-50"
              >
                <RefreshCw className={`h-3.5 w-3.5 ${isSyncing ? 'animate-spin' : ''}`} />
                {pending} pending
              </button>
            )}
          </div>
        </header>

        <main className="flex-1 overflow-auto p-4 pb-24 md:p-6 md:pb-6">
          <Outlet />
        </main>

        {/* Bottom nav - mobile */}
        <nav className="fixed bottom-0 left-0 right-0 z-20 border-t border-slate-200 bg-white md:hidden">
          <div className="flex justify-around py-1">
            {([...navItems.slice(0, 4), { to: "/settings", icon: Settings, label: "Settings" }, ...(user?.is_platform_admin ? [{ to: "/admin", icon: ShieldCheck, label: "Admin" }] : [])]).map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                className={({ isActive }) =>
                  `flex flex-col items-center gap-0.5 px-2 py-2 text-[10px] font-medium ${
                    isActive ? 'text-teal-700' : 'text-slate-500'
                  }`
                }
              >
                <item.icon className="h-5 w-5" />
                {item.label}
              </NavLink>
            ))}
          </div>
        </nav>
      </div>
    </div>
  );
}
