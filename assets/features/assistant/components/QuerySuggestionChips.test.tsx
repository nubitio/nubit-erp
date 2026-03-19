// @vitest-environment jsdom
/**
 * Unit tests for SLICE-013 — QuerySuggestionChips component
 *
 * Coverage:
 *   - Renders suggestion chips returned by useQuerySuggestions
 *   - Calls onSuggestionClick with the correct query when a chip is clicked
 *   - Renders nothing when useQuerySuggestions returns an empty array
 *   - Renders the correct label text on each chip
 *   - Renders the accessible aria-label on each chip
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

// ---------------------------------------------------------------------------
// Module-level mocks
// ---------------------------------------------------------------------------

vi.mock('../hooks/useQuerySuggestions', () => ({
  useQuerySuggestions: vi.fn(),
}));

vi.mock('./QuerySuggestionChips.scss', () => ({}));

// ---------------------------------------------------------------------------
// Imports — after mocks
// ---------------------------------------------------------------------------

import { QuerySuggestionChips } from './QuerySuggestionChips';
import { useQuerySuggestions } from '../hooks/useQuerySuggestions';
import type { QuerySuggestion } from '../hooks/useQuerySuggestions';

const mockUseQuerySuggestions = useQuerySuggestions as ReturnType<typeof vi.fn>;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const mockSuggestions: QuerySuggestion[] = [
  {
    id: 'pos-sold',
    label: '¿Cuánto llevo vendido?',
    query: '¿Cuánto llevo vendido en esta sesión?',
  },
  {
    id: 'pos-customer',
    label: '¿El cliente tiene historial?',
    query: '¿El cliente tiene historial de compras en el sistema?',
  },
  {
    id: 'pos-top',
    label: 'Productos más vendidos',
    query: '¿Cuáles son los productos más vendidos?',
  },
];

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('QuerySuggestionChips', () => {
  beforeEach(() => {
    mockUseQuerySuggestions.mockReset();
  });

  describe('when suggestions are available', () => {
    beforeEach(() => {
      mockUseQuerySuggestions.mockReturnValue({ suggestions: mockSuggestions });
    });

    it('renders a chip for each suggestion', () => {
      render(React.createElement(QuerySuggestionChips, { onSuggestionClick: vi.fn() }));

      expect(screen.getByTestId('query-chip-pos-sold')).toBeTruthy();
      expect(screen.getByTestId('query-chip-pos-customer')).toBeTruthy();
      expect(screen.getByTestId('query-chip-pos-top')).toBeTruthy();
    });

    it('renders the label text on each chip', () => {
      render(React.createElement(QuerySuggestionChips, { onSuggestionClick: vi.fn() }));

      expect(screen.getByText('¿Cuánto llevo vendido?')).toBeTruthy();
      expect(screen.getByText('¿El cliente tiene historial?')).toBeTruthy();
      expect(screen.getByText('Productos más vendidos')).toBeTruthy();
    });

    it('renders the aria-label with the query on each chip', () => {
      render(React.createElement(QuerySuggestionChips, { onSuggestionClick: vi.fn() }));

      const chip = screen.getByTestId('query-chip-pos-sold');
      expect(chip.getAttribute('aria-label')).toBe('¿Cuánto llevo vendido en esta sesión?');
    });

    it('calls onSuggestionClick with the correct query when a chip is clicked', () => {
      const onSuggestionClick = vi.fn();
      render(React.createElement(QuerySuggestionChips, { onSuggestionClick }));

      const chip = screen.getByTestId('query-chip-pos-sold');
      fireEvent.click(chip);

      expect(onSuggestionClick).toHaveBeenCalledTimes(1);
      expect(onSuggestionClick).toHaveBeenCalledWith('¿Cuánto llevo vendido en esta sesión?');
    });

    it('calls onSuggestionClick with the correct query for each chip independently', () => {
      const onSuggestionClick = vi.fn();
      render(React.createElement(QuerySuggestionChips, { onSuggestionClick }));

      fireEvent.click(screen.getByTestId('query-chip-pos-customer'));
      expect(onSuggestionClick).toHaveBeenCalledWith(
        '¿El cliente tiene historial de compras en el sistema?',
      );

      fireEvent.click(screen.getByTestId('query-chip-pos-top'));
      expect(onSuggestionClick).toHaveBeenCalledWith('¿Cuáles son los productos más vendidos?');

      expect(onSuggestionClick).toHaveBeenCalledTimes(2);
    });

    it('renders the container with the correct aria-label', () => {
      const { container } = render(
        React.createElement(QuerySuggestionChips, { onSuggestionClick: vi.fn() }),
      );

      const wrapper = container.querySelector('[aria-label="Sugerencias de consulta"]');
      expect(wrapper).not.toBeNull();
      expect(wrapper?.className).toContain('query-suggestion-chips');
    });
  });

  describe('when suggestions are empty', () => {
    beforeEach(() => {
      mockUseQuerySuggestions.mockReturnValue({ suggestions: [] });
    });

    it('renders nothing (returns null)', () => {
      const { container } = render(
        React.createElement(QuerySuggestionChips, { onSuggestionClick: vi.fn() }),
      );
      expect(container.firstChild).toBeNull();
    });

    it('does not call onSuggestionClick', () => {
      const onSuggestionClick = vi.fn();
      render(React.createElement(QuerySuggestionChips, { onSuggestionClick }));
      expect(onSuggestionClick).not.toHaveBeenCalled();
    });
  });

  describe('with a single suggestion', () => {
    it('renders exactly one chip', () => {
      mockUseQuerySuggestions.mockReturnValue({
        suggestions: [{ id: 'single', label: 'Solo chip', query: 'Solo query' }],
      });

      render(React.createElement(QuerySuggestionChips, { onSuggestionClick: vi.fn() }));

      expect(screen.getByTestId('query-chip-single')).toBeTruthy();
      expect(screen.getAllByRole('button')).toHaveLength(1);
    });
  });
});
