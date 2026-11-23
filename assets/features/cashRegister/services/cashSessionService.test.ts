import { beforeEach, describe, expect, it, vi } from 'vitest';
import { httpClient } from '@shared/api/HttpClient';
import { cashSessionService } from './cashSessionService';

vi.mock('@shared/api/HttpClient', () => ({
  httpClient: { get: vi.fn(), post: vi.fn() },
}));
vi.mock('@nubitio/core', () => ({ getCoreApiBaseUrl: () => '/api/' }));

const get = vi.mocked(httpClient.get);
const reply = (data: unknown) => ({ data, headers: {} }) as never;
const post = vi.mocked(httpClient.post);

beforeEach(() => {
  get.mockReset();
  post.mockReset();
});

describe('cashSessionService', () => {
  it('reads the POS state of the signed-in user, leaving failures to the caller', async () => {
    get.mockResolvedValue(
      reply({ session: null, availableRegisters: [{ id: 3, name: 'Caja 3' }] }),
    );

    const state = await cashSessionService.getPosState();

    expect(get).toHaveBeenCalledWith('/api/cash-pos-state', { errorPresentation: 'inline' });
    expect(state.availableRegisters).toEqual([{ id: 3, name: 'Caja 3' }]);
  });

  it('reads the sales of the shift the user has open', async () => {
    get.mockResolvedValue(reply({ sessionId: 7, count: 1, total: '30.00', sales: [] }));

    const shift = await cashSessionService.getShiftSales();

    expect(get).toHaveBeenCalledWith('/api/cash-pos-state/sales', { errorPresentation: 'inline' });
    expect(shift.count).toBe(1);
  });

  it('opens a register with the opening cash rounded to cents', async () => {
    post.mockResolvedValue(reply({}));

    await cashSessionService.open(3, 150.456);

    expect(post).toHaveBeenCalledWith(
      '/api/cash-registers/3/open',
      { id: 3, openingBalance: 150.46 },
      { errorPresentation: 'inline' },
    );
  });

  it('registers a movement against the session and the category by IRI, the amount as a decimal string', async () => {
    post.mockResolvedValue(reply({}));

    await cashSessionService.addMovement({
      sessionId: 371,
      categoryId: 6,
      amount: 12.5,
      description: 'Bolsas',
    });

    expect(post).toHaveBeenCalledWith(
      '/api/cash-movements',
      {
        cashSession: '/api/cash-sessions/371',
        category: '/api/cash-movement-categories/6',
        amount: '12.50',
        description: 'Bolsas',
      },
      { errorPresentation: 'inline' },
    );
  });

  it('offers only the active reasons a person may pick, never the ones the system records itself', async () => {
    get.mockResolvedValue(
      reply([
        { id: 1, name: 'Gasto menor', type: 'expense', active: true, manual: true },
        { id: 2, name: 'Inactiva', type: 'expense', active: false, manual: true },
        { id: 3, name: 'Venta en efectivo', type: 'income', active: true, manual: false },
      ]),
    );

    const categories = await cashSessionService.getMovementCategories();

    expect(categories.map((category) => category.name)).toEqual(['Gasto menor']);
  });
});
