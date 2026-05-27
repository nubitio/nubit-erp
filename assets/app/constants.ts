// eFact application-level constants.
// Display labels should use i18n keys instead of string constants.
export const BASE_URL = '/' as const;

// NOTE: API base URL is now configured exclusively via CoreConfigProvider + getCoreApiBaseUrl()
// from @nubit/core. We no longer export API_BASE_URL here to encourage using the Nubit config system.
