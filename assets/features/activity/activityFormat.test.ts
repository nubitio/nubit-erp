import { DateTime } from 'luxon';
import { describe, expect, it } from 'vitest';
import {
  activityIcon,
  buildActivityQuery,
  DEFAULT_FILTERS,
  filtersFromUrl,
  formatTime,
  groupByDay,
  rangeToDates,
  summaryTone,
  type ActivityItem,
} from './activityFormat';

// 2026-11-07 15:00 in Lima (UTC-5) = 20:00 UTC
const now = DateTime.fromISO('2026-11-07T15:00:00', { zone: 'America/Lima' });

const item = (id: number, occurredAt: string): ActivityItem => ({
  id,
  occurredAt,
  actor: 'ana',
  type: 'auth.login',
  sensitive: false,
  summary: `s${id}`,
  detail: null,
  ip: null,
});

describe('formatTime', () => {
  it('shows the business time, not UTC', () => {
    expect(formatTime('2026-11-07T20:12:00Z')).toBe('15:12');
    expect(formatTime('2026-11-07T02:04:00Z')).toBe('21:04');
  });

  it('returns an empty string for an unreadable date instead of throwing', () => {
    expect(formatTime('nope')).toBe('');
  });
});

describe('groupByDay', () => {
  it('groups by the Lima calendar day, so late evening UTC stays on the right day', () => {
    // 03:30Z on the 8th is still the 7th in Lima
    const groups = groupByDay(
      [
        item(1, '2026-11-08T03:30:00Z'),
        item(2, '2026-11-07T14:00:00Z'),
        item(3, '2026-11-06T12:00:00Z'),
      ],
      now,
    );

    expect(groups.map((g) => g.key)).toEqual(['2026-11-07', '2026-11-06']);
    expect(groups[0].items.map((i) => i.id)).toEqual([1, 2]);
  });

  it('labels today and yesterday and spells older days in Spanish uppercase', () => {
    const groups = groupByDay(
      [
        item(1, '2026-11-07T14:00:00Z'),
        item(2, '2026-11-06T14:00:00Z'),
        item(3, '2026-11-01T14:00:00Z'),
      ],
      now,
    );

    expect(groups[0].heading).toBe('HOY · SÁBADO 7 DE NOVIEMBRE');
    expect(groups[1].heading).toBe('AYER · VIERNES 6 DE NOVIEMBRE');
    expect(groups[2].heading).toBe('DOMINGO 1 DE NOVIEMBRE');
  });

  it('skips unreadable dates and handles an empty list', () => {
    expect(groupByDay([item(1, 'bad')], now)).toEqual([]);
    expect(groupByDay([], now)).toEqual([]);
  });
});

describe('rangeToDates', () => {
  it('turns a range into inclusive business-day bounds', () => {
    expect(rangeToDates('today', now)).toEqual({ from: '2026-11-07', to: '2026-11-07' });
    expect(rangeToDates('7d', now)).toEqual({ from: '2026-11-01', to: '2026-11-07' });
    expect(rangeToDates('30d', now)).toEqual({ from: '2026-10-09', to: '2026-11-07' });
    expect(rangeToDates('all', now)).toEqual({});
  });

  it('uses the Lima day even when it is already tomorrow in UTC', () => {
    const lateLima = DateTime.fromISO('2026-11-07T23:30:00', { zone: 'America/Lima' }); // 04:30Z on the 8th
    expect(rangeToDates('today', lateLima).to).toBe('2026-11-07');
  });
});

describe('buildActivityQuery', () => {
  it('sends only what is set', () => {
    expect(buildActivityQuery({ ...DEFAULT_FILTERS, range: 'all' }, undefined, now)).toBe(
      'type=sensitive',
    );
  });

  it('encodes actor, search and dates and the page', () => {
    const query = buildActivityQuery(
      { type: 'auth', actor: 'ana', range: 'today', search: '  50% off & más  ' },
      2,
      now,
    );
    const params = new URLSearchParams(query);

    expect(params.get('type')).toBe('auth');
    expect(params.get('actor')).toBe('ana');
    expect(params.get('q')).toBe('50% off & más');
    expect(params.get('from')).toBe('2026-11-07');
    expect(params.get('page')).toBe('2');
  });
});

describe('activityIcon', () => {
  it('picks an icon per kind of event, with a fallback by family', () => {
    expect(activityIcon('auth.login_failed')).toBe('ph ph-warning');
    expect(activityIcon('auth.totp_enabled')).toBe('ph ph-shield-check');
    expect(activityIcon('role.permissions_changed')).toBe('ph ph-user-gear');
    expect(activityIcon('auth.password_reset')).toBe('ph ph-key');
    expect(activityIcon('user.created')).toBe('ph ph-user');
  });
});

describe('summaryTone', () => {
  it('flags failures and leaves the rest neutral', () => {
    expect(summaryTone('auth.login_failed')).toBe('danger');
    expect(summaryTone('auth.login')).toBe('default');
  });
});

describe('filtersFromUrl', () => {
  it('presets the filters a link carries', () => {
    expect(filtersFromUrl(new URLSearchParams('actor=ana&type=all&range=all'))).toEqual({
      ...DEFAULT_FILTERS,
      actor: 'ana',
      type: 'all',
      range: 'all',
    });
  });

  it('keeps the defaults for a missing or unknown value', () => {
    expect(filtersFromUrl(new URLSearchParams(''))).toEqual(DEFAULT_FILTERS);
    expect(filtersFromUrl(new URLSearchParams('type=nonsense&range=yesterday'))).toEqual(
      DEFAULT_FILTERS,
    );
  });
});
