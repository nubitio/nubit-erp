import type { DataRecord } from '@nubit/core';

export default interface AuthResponse {
  token?: string;
  refreshToken?: string;
  user?: DataRecord;
  branch?: {
    id: string;
    name: string | null;
  };
}
