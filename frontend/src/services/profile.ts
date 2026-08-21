import api from './api';
import type { User } from '../types';

export async function getProfile() {
  const { data } = await api.get<{ data: User }>('/profile');
  return data.data;
}

export async function updateProfile(payload: Partial<User>) {
  const { data } = await api.put<{ data: User }>('/profile', payload);
  localStorage.setItem('mystocks_user', JSON.stringify(data.data));
  return data.data;
}

export async function uploadAvatar(file: File) {
  const form = new FormData();
  form.append('image', file);

  const { data } = await api.post<{ data: User }>('/profile/avatar', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });

  localStorage.setItem('mystocks_user', JSON.stringify(data.data));
  return data.data;
}
