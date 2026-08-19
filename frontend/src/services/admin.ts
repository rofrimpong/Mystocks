import api from './api'; import type {User} from '../types';
export async function getAdminSummary(){const {data}=await api.get('/admin/summary'); return data.data;}
export async function getAdminUsers(search=''){const {data}=await api.get('/admin/users',{params:{search,per_page:50}}); return data.data as User[];}
export async function updateAdminUser(id:string,payload:{is_active?:boolean;is_platform_admin?:boolean}){const {data}=await api.patch(`/admin/users/${id}`,payload); return data.data as User;}
export async function getAdminBusinesses(){const {data}=await api.get('/admin/businesses',{params:{per_page:50}}); return data.data as Array<{id:string;name:string;status:string;users_count:number}>;}
export async function updateAdminBusiness(id:string,status:string){const {data}=await api.patch(`/admin/businesses/${id}`,{status}); return data.data;}
