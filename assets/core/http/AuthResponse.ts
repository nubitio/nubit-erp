export default interface AuthResponse {
  token?: string;
  refreshToken?: string;
  user?: any;
  branch?: {
    id: string;
    name: string | null;
  };
}
