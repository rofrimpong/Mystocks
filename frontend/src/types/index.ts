export interface User {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
  is_platform_admin: boolean;
  is_active: boolean;
  locale: string;
  timezone: string;
  avatar_path?: string | null;
  avatar_url?: string | null;
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

export interface SaleItem {
  id: string;
  product_id: string;
  product_name: string;
  product_sku?: string | null;
  quantity: string;
  unit_selling_price: string;
  discount_amount: string;
  line_total: string;
}

export interface SalePayment {
  id: string;
  method: string;
  amount: string;
  reference?: string | null;
  provider?: string | null;
  paid_at?: string | null;
}

export interface Sale {
  id: string;
  sale_number: string;
  status: string;
  subtotal: string;
  discount_amount: string;
  tax_amount: string;
  total: string;
  payment_status: string;
  sold_at?: string | null;
  branch?: {
    id: string;
    name: string;
  };
  cashier?: {
    id: string;
    name: string;
  };
  customer?: {
    id: string;
    name: string;
    phone?: string | null;
  };
  items?: SaleItem[];
  payments?: SalePayment[];
}
