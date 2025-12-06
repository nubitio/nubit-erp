import { EmployeeDto } from './employee.dto';
import { SpecialtyDto } from './specialty.dto';

export interface PhysicianDto {
  id: number;
  employee: EmployeeDto;
  specialty: SpecialtyDto;
  licence: string;
  full_name: string;
}
