/** A Hydra/API Platform collection: a bare array, `member` (JSON-LD 1.1) or `hydra:member`. */
export type HydraCollection<T> = T[] | { member?: T[]; 'hydra:member'?: T[] };

/** The items of a collection response, whichever shape the API answered with. */
export function hydraMembers<T>(data: HydraCollection<T> | null | undefined): T[] {
  if (!data) return [];
  if (Array.isArray(data)) return data;
  return data.member ?? data['hydra:member'] ?? [];
}
