import { DepartmentDto } from './department.dto';

export interface PositionDto {
  id: number;
  department: DepartmentDto;
  name: string;
  description: string;
}
