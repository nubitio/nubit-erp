const DNS_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

function uuidBytes(uuid: string): Uint8Array {
  const hex = uuid.replaceAll('-', '');
  return Uint8Array.from({ length: 16 }, (_, index) =>
    Number.parseInt(hex.slice(index * 2, index * 2 + 2), 16),
  );
}

function formatUuid(bytes: Uint8Array): string {
  const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Derive the same UUID v5 on every device for a tenant's restaurant order.
 * Sales has a unique index on its offline retry key, so simultaneous checkout
 * requests cannot create duplicate sales for that order.
 */
export async function getCheckoutIdempotencyKey(orderId: string): Promise<string> {
  const namespace = uuidBytes(DNS_NAMESPACE);
  const name = new TextEncoder().encode(`nubit:restaurant-order:${orderId}`);
  const input = new Uint8Array(namespace.length + name.length);
  input.set(namespace);
  input.set(name, namespace.length);

  const digest = new Uint8Array(await crypto.subtle.digest('SHA-1', input));
  const uuid = digest.slice(0, 16);
  uuid[6] = (uuid[6] & 0x0f) | 0x50;
  uuid[8] = (uuid[8] & 0x3f) | 0x80;

  return formatUuid(uuid);
}
