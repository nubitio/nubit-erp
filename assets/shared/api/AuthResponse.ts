export default interface AuthResponse {
  token?: string;
  refreshToken?: string;
  user?: Record<string, unknown>;
  branch?: {
    id: string;
    name: string | null;
  };
}
