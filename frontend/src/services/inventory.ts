import api from './api';

export interface InventoryBalance {
  id: string;
  product_id: string;
  branch_id: string;
  quantity: string;
  reserved_quantity: string;
  available_quantity: string;
  average_cost: string;
  is_low_stock: boolean;
  product?: {
    id: string;
    name: string;
    sku?: string | null;
    unit: string;
    minimum_stock_level: string;
  };
  branch?: {
    id: string;
    name: string;
  };
}

export async function fetchBalances(params?: {
  search?: string;
  branch_id?: string;
  low_stock?: boolean;
  per_page?: number;
}): Promise<{ data: InventoryBalance[]; meta: { total: number } }> {
  const { data } = await api.get('/inventory/balances', { params });
  return data;
}

export async function adjustStock(payload: {
  product_id: string;
  branch_id: string;
  direction: 'in' | 'out';
  quantity: number;
  reason: string;
  unit_cost?: number;
}) {
  const { data } = await api.post('/inventory/adjust', payload);
  return data;
}

export async function openingStock(payload: {
  product_id: string;
  branch_id: string;
  quantity: number;
  unit_cost?: number;
  reason?: string;
}) {
  const { data } = await api.post('/inventory/opening-stock', payload);
  return data;
}

export interface InventoryMovement {
  id: string; product_id: string; type: string; direction: 'in'|'out'; quantity: string; unit_cost?: string|null; reference_number?: string|null; reason?: string|null; occurred_at: string; user?: {id:string;name:string}; product?: {id:string;name:string;sku?:string|null};
}
export async function fetchMovements(params?: {product_id?:string;branch_id?:string;type?:string;per_page?:number}) {
  const {data}=await api.get('/inventory/movements',{params}); return data as {data:InventoryMovement[];meta:{total:number}};
}
