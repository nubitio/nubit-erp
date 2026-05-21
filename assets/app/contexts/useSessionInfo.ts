import { userInformation } from '@nubit/core';

/**
 * Returns branch and user info from the active eFact session.
 * Reads localStorage synchronously — safe to call inside React components
 * on render, but does not trigger re-renders when the values change.
 * Use in components that display session data (e.g., Dashboard, POS header).
 */
export function useSessionInfo() {
  return userInformation();
}
