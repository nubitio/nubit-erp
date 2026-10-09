/** Normalize API Platform / Hydra / plain-array collection responses. */
export function parseApiCollection<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) {
    return payload as T[];
  }

  if (payload && typeof payload === 'object') {
    const record = payload as Record<string, unknown>;
    const member = record['hydra:member'] ?? record.member;
    if (Array.isArray(member)) {
      return member as T[];
    }
  }

  return [];
}
