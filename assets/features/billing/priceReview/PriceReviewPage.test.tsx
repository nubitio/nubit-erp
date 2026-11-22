import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PriceReviewPage } from './PriceReviewPage';
import type { PriceReviewLine } from './priceReview';
import { usePriceReview } from './usePriceReview';

vi.mock('./usePriceReview', () => ({ usePriceReview: vi.fn() }));

const line: PriceReviewLine = {
  lineId: 1,
  saleId: 'sale-1',
  document: 'N001-3624',
  issuedAt: '2026-11-21T11:23:37+00:00',
  seller: 'marta',
  product: 'POLLO ENTERO X KG',
  quantity: '25.490',
  chargedPrice: '9.80',
  expectedPrice: '10.10',
  difference: '-0.30',
  differencePercent: '-3.0',
  impact: '-7.65',
};

const mockQuery = (state: Record<string, unknown>) =>
  vi.mocked(usePriceReview).mockReturnValue(state as never);

const renderPage = () =>
  render(
    <MemoryRouter>
      <PriceReviewPage />
    </MemoryRouter>,
  );

afterEach(cleanup);

describe('PriceReviewPage', () => {
  it('lists the lines with what was charged against the price in force', () => {
    mockQuery({ data: [line], isPending: false, isError: false });
    renderPage();

    expect(screen.getByRole('link', { name: 'N001-3624' }).getAttribute('href')).toBe(
      '/billing/sales/sale-1',
    );
    expect(screen.getByText('POLLO ENTERO X KG')).toBeTruthy();
    expect(screen.getByText('-3.0 %')).toBeTruthy();
    expect(screen.getAllByText('marta').length).toBeGreaterThan(0);
  });

  it('says there is nothing to review when there are no lines', () => {
    mockQuery({ data: [], isPending: false, isError: false });
    renderPage();

    expect(screen.getByText('Nada que revisar')).toBeTruthy();
  });

  it('shows an error instead of an empty list when the request fails', () => {
    mockQuery({ data: undefined, isPending: false, isError: true });
    renderPage();

    expect(screen.getByText(/No se pudo cargar el listado/)).toBeTruthy();
    expect(screen.queryByText('Nada que revisar')).toBeNull();
  });
});
