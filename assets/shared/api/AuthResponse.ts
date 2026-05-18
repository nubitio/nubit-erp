import type { DataRecord } from '@core/data/DataRecord';

export default interface AuthResponse {
  token?: string;
  refreshToken?: string;
  user?: DataRecord;
  branch?: {
    id: string;
    name: string | null;
  };
}
