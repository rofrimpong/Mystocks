import api from './api';

export interface ExpenseCategory {
  id: string;
  name: string;
  slug?: string;
  is_active: boolean;
}

export interface Expense {
  id: string;
  amount: string;
  description?: string | null;
  payment_method: string;
  reference?: string | null;
  expense_date: string;
  category?: ExpenseCategory;
  branch_id?: string | null;
}

export async function fetchExpenseCategories(): Promise<ExpenseCategory[]> {
  const { data } = await api.get<{ data: ExpenseCategory[] }>('/expense-categories');
  return data.data;
}

export async function createExpenseCategory(name: string) {
  const { data } = await api.post('/expense-categories', { name });
  return data;
}

export async function fetchExpenses(params?: {
  category_id?: string;
  from?: string;
  to?: string;
  per_page?: number;
}): Promise<{ data: Expense[]; meta: { total: number } }> {
  const { data } = await api.get('/expenses', { params });
  return data;
}

export async function createExpense(payload: {
  category_id: string;
  amount: number;
  description?: string;
  payment_method?: string;
  reference?: string;
  expense_date?: string;
  branch_id?: string;
}) {
  const { data } = await api.post('/expenses', payload);
  return data;
}
