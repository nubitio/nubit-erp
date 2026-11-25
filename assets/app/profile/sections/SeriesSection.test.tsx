import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { branchService, seriesService, voucherTypeService } from '../organizationService';
import { SeriesSection } from './SeriesSection';

vi.mock('@shared/permissions', () => ({ hasPermission: () => true }));
vi.mock('@shared/lib/SharedUtils', () => ({ default: vi.fn() }));
vi.mock('../organizationService', () => ({
  branchIri: (id: string) => `/api/branches/${id}`,
  voucherTypeIri: (code: string) => `/api/voucher-types/${code}`,
  branchService: { list: vi.fn() },
  voucherTypeService: { list: vi.fn() },
  seriesService: { list: vi.fn(), create: vi.fn() },
}));

afterEach(() => {
  cleanup();
  vi.resetAllMocks();
});

const branch = { id: 'b2', name: 'Arequipa', address: null, phone: null, isActive: true };
const type = { code: '01', name: 'Factura', isActive: true };
const saved = {
  id: 1,
  branch,
  voucherType: type,
  series: 'F002',
  associatedSeries: null,
  isActive: true,
};

async function fillNewSeries() {
  const user = userEvent.setup();
  vi.mocked(seriesService.list).mockResolvedValueOnce([]);
  vi.mocked(branchService.list).mockResolvedValue([{ ...branch, id: 'b1', name: 'Lima' }, branch]);
  vi.mocked(voucherTypeService.list).mockResolvedValue([type]);
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <QueryClientProvider client={client}>
      <SeriesSection />
    </QueryClientProvider>,
  );

  await user.click(await screen.findByRole('button', { name: 'Nueva serie' }));
  const combos = screen.getAllByRole('combobox');
  await user.click(combos[0]);
  await user.click(await screen.findByRole('option', { name: 'Arequipa' }));
  await user.click(combos[1]);
  await user.click(await screen.findByRole('option', { name: 'Factura (01)' }));
  fireEvent.change(screen.getByLabelText('Serie', { exact: false, selector: '#series-code' }), {
    target: { value: 'F002' },
  });
  return user;
}

describe('SeriesSection', () => {
  it('shows the series for the selected branch before the collection refetch finishes', async () => {
    vi.mocked(seriesService.list).mockImplementation(() => new Promise(() => {}));
    vi.mocked(seriesService.create).mockResolvedValue({ data: saved, headers: {} });
    const user = await fillNewSeries();
    await user.click(screen.getByRole('button', { name: 'Crear serie' }));

    expect(await screen.findByText('F002 · Factura')).toBeTruthy();
    expect(screen.getByText('Arequipa')).toBeTruthy();
    expect(seriesService.create).toHaveBeenCalledWith(
      expect.objectContaining({ branch: '/api/branches/b2' }),
    );
  });

  it('shows a duplicate found by the API and clears it when the branch changes', async () => {
    const message =
      'Ya existe una serie con este código para ese tipo de comprobante en esta sucursal.';
    vi.mocked(seriesService.create).mockRejectedValue({
      status: 422,
      data: { violations: [{ propertyPath: 'series', message }] },
    });
    const user = await fillNewSeries();
    await user.click(screen.getByRole('button', { name: 'Crear serie' }));
    expect(await screen.findByText(message)).toBeTruthy();

    await user.click(screen.getAllByRole('combobox')[0]);
    await user.click(await screen.findByRole('option', { name: 'Lima' }));
    expect(screen.queryByText(message)).toBeNull();
  });
});
