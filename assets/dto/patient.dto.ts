import { PriceListDto } from './price-list.dto';
import { CustomerDto } from './customer.dto';

export interface PatientDto {
  id: number;
  customer: CustomerDto;
  type_id: number;
  insurance_provider: string;
  medical_record_number: string;
  created_by: number;
  updated_by: number;
  created_at: string;
  updated_at: string;
  ruc: string;
  razon_social: string;
  domicilio_fiscal: string;
  telefono: string;
  email: string;
  imagen: string;
  price_list: PriceListDto;
  estado: boolean;
  full_name: string;
}
