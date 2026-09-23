import React from 'react';
import type { OrderItemMock } from './types';

function minutesSince(iso: string, now: number): number {
  return Math.max(0, Math.floor((now - new Date(iso).getTime()) / 60000));
}

function urgency(minutes: number): 'fresh' | 'due' | 'late' {
  if (minutes >= 10) return 'late';
  if (minutes >= 5) return 'due';
  return 'fresh';
}

interface KitchenTicketCardProps {
  tableCode: string;
  items: OrderItemMock[];
  now: number;
  onMarkReady: (itemId: string) => void;
}

export function KitchenTicketCard({ tableCode, items, now, onMarkReady }: KitchenTicketCardProps) {
  const oldestSentAt = items
    .map((it) => it.sentAt)
    .filter((v): v is string => Boolean(v))
    .sort()[0];
  const elapsed = oldestSentAt ? minutesSince(oldestSentAt, now) : 0;

  return (
    <article className={`restaurant-kds-ticket is-${urgency(elapsed)}`}>
      <header>
        <span className="restaurant-kds-ticket__table">Mesa {tableCode}</span>
        <span className="restaurant-kds-ticket__time">{elapsed}&apos;</span>
      </header>
      <ul>
        {items.map((item) => (
          <li key={item.id} className={item.status === 'listo' ? 'is-ready' : ''}>
            <span>
              {item.qty}× {item.productName}
            </span>
            {item.status !== 'listo' ? (
              <button type="button" onClick={() => onMarkReady(item.id)}>
                Listo
              </button>
            ) : (
              <span className="restaurant-kds-ticket__check">✓</span>
            )}
          </li>
        ))}
      </ul>
    </article>
  );
}
