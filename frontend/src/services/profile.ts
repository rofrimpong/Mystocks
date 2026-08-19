import api from './api'; import type {User} from '../types';
export async function getProfile(){const {data}=await api.get<{data:User}>('/profile'); return data.data;}
export async function updateProfile(payload:Partial<User>){const {data}=await api.put<{data:User}>('/profile',payload); localStorage.setItem('mystocks_user',JSON.stringify(data.data)); return data.data;}
