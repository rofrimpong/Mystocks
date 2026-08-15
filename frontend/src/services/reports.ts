import api from './api';

export interface ProfitSummary {
  revenue: string;
  cost_of_goods_sold: string;
  gross_profit: string;
  expenses: string;
  net_profit: string;
  sales_count: number;
  from?: string | null;
  to?: string | null;
}

export interface BestSeller {
  product_id: string;
  product_name: string;
  product_sku?: string | null;
  total_quantity: string;
  total_revenue: string;
  total_profit: string;
}

export async function fetchProfitSummary(params?: {
  from?: string;
  to?: string;
  branch_id?: string;
}): Promise<ProfitSummary> {
  const { data } = await api.get<{ data: ProfitSummary }>('/reports/profit', { params });
  return data.data;
}

export async function fetchBestSellers(params?: {
  from?: string;
  to?: string;
  limit?: number;
}): Promise<BestSeller[]> {
  const { data } = await api.get<{ data: BestSeller[] }>('/reports/best-sellers', { params });
  return data.data;
}

export async function fetchLowStockCount(): Promise<number> {
  const { data } = await api.get('/reports/low-stock', { params: { per_page: 1 } });
  return data.meta?.total ?? data.data?.length ?? 0;
}
