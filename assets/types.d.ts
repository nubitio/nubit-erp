export {};

declare global {
  interface Window {
    app_logo?: string;
    app_version?: string;
    /** True when the identity module (2FA, invitations, password recovery) is enabled server-side. */
    app_identity?: boolean;
  }
}
