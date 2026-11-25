import { beforeEach, describe, expect, it, vi } from 'vitest';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@nubitio/core', () => ({ getCoreApiBaseUrl: () => '/api/' }));
vi.mock('@shared/api/HttpClient', () => ({ httpClient: { get } }));

import { seriesService } from './organizationService';

describe('organization service collections', () => {
  beforeEach(() => get.mockReset());

  it('loads every page of voucher series instead of silently truncating at 200', async () => {
    const firstPage = Array.from({ length: 200 }, (_, index) => ({ id: index + 1 }));
    const finalRecord = { id: 201 };
    get
      .mockResolvedValueOnce({
        data: { member: firstPage, totalItems: 201 },
      })
      .mockResolvedValueOnce({
        data: { member: [finalRecord], totalItems: 201 },
      });

    const series = await seriesService.list();

    expect(series).toHaveLength(201);
    expect(series.at(-1)).toEqual(finalRecord);
    expect(get).toHaveBeenNthCalledWith(1, '/api/voucher-series?itemsPerPage=200&page=1');
    expect(get).toHaveBeenNthCalledWith(2, '/api/voucher-series?itemsPerPage=200&page=2');
  });
});
