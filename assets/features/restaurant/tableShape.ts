export type TableShape = 'round' | 'square' | 'long';

/** Mirrors the table shapes designed for the floor plan: round (≤2), square (≤4), long (5+). */
export function shapeFor(seats: number): TableShape {
  if (seats <= 2) return 'round';
  if (seats <= 4) return 'square';
  return 'long';
}
