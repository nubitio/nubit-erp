import { PriceListDto } from './price-list.dto';

export interface CustomerDto {
  id: number;
  ruc: string;
  razon_social: string;
  domicilio_fiscal: string;
  telefono: string;
  email: string;
  imagen: string;
  price_list: PriceListDto;
  estado: boolean;
}
