const STORAGE_PREFIX = 'restaurant.checkout.idempotency.';

const newIdempotencyKey = (): string => {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }

  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
    const random = Math.floor(Math.random() * 16);
    return (char === 'x' ? random : (random & 0x3) | 0x8).toString(16);
  });
};

/**
 * Keep one sale idempotency key per open restaurant order across retries and
 * page reloads. Sales can be persisted even when closing the comanda fails.
 */
export function getCheckoutIdempotencyKey(orderId: string): string {
  const storageKey = `${STORAGE_PREFIX}${orderId}`;
  const existingKey = localStorage.getItem(storageKey);

  if (existingKey) {
    return existingKey;
  }

  const idempotencyKey = newIdempotencyKey();
  localStorage.setItem(storageKey, idempotencyKey);

  return idempotencyKey;
}

export function clearCheckoutIdempotencyKey(orderId: string): void {
  localStorage.removeItem(`${STORAGE_PREFIX}${orderId}`);
}
