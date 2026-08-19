import api from './api';
export async function restockProduct(payload:{branch_id:string;product_id:string;quantity:number;unit_cost:number;supplier_id?:string;supplier_invoice_number?:string;purchased_at?:string;notes?:string}) {
 const {branch_id,product_id,quantity,unit_cost,...rest}=payload;
 const {data}=await api.post('/purchases',{branch_id,...rest,items:[{product_id,quantity,unit_cost}]}); return data;
}
