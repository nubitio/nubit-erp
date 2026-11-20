import { useRef } from 'react';

export const generateIdempotencyKey = (): string => {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
    const random = (Math.random() * 16) | 0;
    const value = char === 'x' ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
};

/**
 * One key per distinct request. The key stays the same while `signature` (what the request carries)
 * does not change, so retrying after a timeout reuses it and the server can recognise the repeat.
 * Edit the request and the key changes, because it is no longer the same request.
 * Call `renew()` after a success so the next request starts fresh.
 */
export function useIdempotencyKey(signature: string): { current: () => string; renew: () => void } {
  const ref = useRef<{ signature: string; key: string } | null>(null);
  return {
    current: () => {
      if (ref.current?.signature !== signature) {
        ref.current = { signature, key: generateIdempotencyKey() };
      }
      return ref.current.key;
    },
    renew: () => {
      ref.current = null;
    },
  };
}
