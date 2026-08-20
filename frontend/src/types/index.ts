export interface User {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
  is_platform_admin: boolean;
  is_active: boolean;
  locale: string;
  timezone: string;
}

export interface Business {
  id: string;
  name: string;
  currency: string;
  status: string;
  is_owner?: boolean;
  role?: 'owner' | 'manager' | 'cashier' | 'salesperson' | 'inventory_officer' | 'staff';
  branch_id?: string | null;
  trial_ends_at?: string | null;
}

export interface Branch {
  id: string;
  name: string;
  code?: string | null;
  is_head_office: boolean;
  status: string;
}

export interface Product {
  id: string;
  name: string;
  sku?: string | null;
  barcode?: string | null;
  unit: string;
  buying_price: string;
  selling_price: string;
  minimum_stock_level: string;
  track_inventory: boolean;
  is_active: boolean;
  is_service: boolean;
  category_id?: string | null;
  preferred_supplier_id?: string | null;
  image_path?: string | null;
  image_url?: string | null;
  category?: { id: string; name: string } | null;
  preferred_supplier?: { id: string; name: string } | null;
}

export interface DashboardData {
  today: {
    revenue: string;
    cost_of_goods_sold: string;
    gross_profit: string;
    expenses: string;
    net_profit: string;
    sales_count: number;
  };
  stock_value: string;
  low_stock_count: number;
  total_products: number;
  business: {
    id: string;
    name: string;
    currency: string;
  };
}

export interface AuthResponse {
  message: string;
  data: {
    user: User;
    businesses?: Business[];
    business?: Business;
    branch?: Branch;
    token: string;
    token_type: string;
  };
}

export type OfflineOperationType = 'sale' | 'inventory_adjustment' | 'opening_stock';

export interface OfflineOperation {
  id: string;
  idempotency_key: string;
  operation_type: OfflineOperationType;
  payload: Record<string, unknown>;
  client_created_at: string;
  status: 'pending' | 'syncing' | 'synced' | 'conflict' | 'failed';
  conflict_reason?: string;
}
