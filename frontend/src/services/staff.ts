import api from './api';

export type StaffRole =
  | 'manager'
  | 'cashier'
  | 'salesperson'
  | 'inventory_officer';

export interface StaffMember {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
  branch_id?: string | null;
  is_owner: boolean;
  role: 'owner' | StaffRole;
  is_active: boolean;
}

export interface CreateStaffPayload {
  name: string;
  email: string;
  phone?: string | null;
  password: string;
  branch_id: string;
  role: StaffRole;
}

export interface UpdateStaffPayload {
  name?: string;
  phone?: string | null;
  password?: string;
  branch_id?: string;
  role?: StaffRole;
  is_active?: boolean;
}

export async function fetchStaff(): Promise<StaffMember[]> {
  const { data } = await api.get<{ data: StaffMember[] }>('/staff');
  return data.data;
}

export async function createStaff(
  payload: CreateStaffPayload
): Promise<StaffMember> {
  const { data } = await api.post<{ data: StaffMember }>('/staff', payload);
  return data.data;
}

export async function updateStaff(
  id: string,
  payload: UpdateStaffPayload
): Promise<void> {
  await api.put(`/staff/${id}`, payload);
}
