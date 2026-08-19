import api from './api';
import type { Product } from '../types';

export async function fetchProducts(params?: Record<string, unknown>) { const {data}=await api.get<{data:Product[];meta:{total:number}}>('/products',{params}); return data; }
export async function createProduct(payload: Record<string, unknown>) { const {data}=await api.post<{data:Product}>('/products',payload); return data.data; }
export async function updateProduct(id:string,payload:Record<string,unknown>) { const {data}=await api.put<{data:Product}>(`/products/${id}`,payload); return data.data; }
export async function archiveProduct(id:string) { const {data}=await api.delete(`/products/${id}`); return data; }
export async function uploadProductImage(id:string,file:File) { const form=new FormData(); form.append('image',file); const {data}=await api.post<{data:Product}>(`/products/${id}/image`,form,{headers:{'Content-Type':'multipart/form-data'}}); return data.data; }
export async function fetchCategories() { const {data}=await api.get('/categories',{params:{active_only:true,per_page:100}}); return data.data as Array<{id:string;name:string}>; }
