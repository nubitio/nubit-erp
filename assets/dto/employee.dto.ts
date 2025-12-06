import { PositionDto } from './position.dto';

export interface EmployeeDto {
  id: number;
  id_number: string;
  first_name: string;
  last_name: string;
  gender: number;
  birthdate: string;
  address: string;
  phone: string;
  hire_date: string;
  position: PositionDto;
  salary: number;
  active: boolean;
  full_name: string;
}
