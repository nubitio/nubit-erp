import type { CartItem } from '../types';

/** An order parked while the cashier serves someone else. Local to this browser. */
export interface HeldSale {
  id: string;
  number: number;
  cart: Omit<CartItem, 'photo'>[];
  discountPercent: number;
  note: string;
  customerId: number | null;
  customerName: string | null;
  total: number;
  heldAt: string;
}

const heldKey = (storeId: string) => `pos_held_${storeId}`;
const seqKey = (storeId: string) => `pos_ticket_seq_${storeId}`;

export function readHeldSales(storeId: string): HeldSale[] {
  try {
    const parsed: unknown = JSON.parse(localStorage.getItem(heldKey(storeId)) ?? '[]');
    return Array.isArray(parsed) ? (parsed as HeldSale[]) : [];
  } catch {
    return [];
  }
}

export function writeHeldSales(storeId: string, sales: HeldSale[]): void {
  try {
    if (sales.length === 0) localStorage.removeItem(heldKey(storeId));
    else localStorage.setItem(heldKey(storeId), JSON.stringify(sales));
  } catch {
    // Safari private / quota — the order stays in memory for this session
  }
}

/** Next local order number for this branch; only a visual reference, not a fiscal series. */
export function nextTicketNumber(storeId: string): number {
  const current = Number(localStorage.getItem(seqKey(storeId)) ?? '0');
  const next = (Number.isFinite(current) ? current : 0) + 1;
  try {
    localStorage.setItem(seqKey(storeId), String(next));
  } catch {
    // ignore
  }
  return next;
}

export const formatTicketNumber = (number: number): string => `#${String(number).padStart(5, '0')}`;

/** Freezes the order in progress; the ticket number doubles as the identifier. */
export function buildHeldSale(order: Omit<HeldSale, 'id' | 'heldAt'>): HeldSale {
  return { ...order, id: `held-${order.number}`, heldAt: new Date().toISOString() };
}
