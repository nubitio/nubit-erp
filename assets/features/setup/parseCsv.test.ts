import { describe, expect, it } from 'vitest';
import { csvObjects, parseCsv } from './parseCsv';

describe('parseCsv', () => {
  it('splits quoted commas', () => {
    expect(parseCsv('ruc,nombre\n20100100101,"Andina, SAC"')).toEqual([
      ['ruc', 'nombre'],
      ['20100100101', 'Andina, SAC'],
    ]);
  });
});

describe('csvObjects', () => {
  it('maps headers case-insensitively', () => {
    const rows = csvObjects('RUC,Nombre\n20100100101,Andina Retail SAC');
    expect(rows[0]).toEqual({ ruc: '20100100101', nombre: 'Andina Retail SAC' });
  });
});
