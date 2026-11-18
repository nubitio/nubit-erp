import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PieceCounter } from './PieceCounter';

describe('PieceCounter', () => {
  afterEach(cleanup);

  it('shows the count and names the product in every control', () => {
    render(<PieceCounter pieces={2} productName="Pechuga" onChange={vi.fn()} />);

    expect(screen.getByRole('group', { name: 'Unidades de Pechuga' })).toBeTruthy();
    expect(screen.getByText('2 und')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Sumar unidad de Pechuga' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Restar unidad de Pechuga' })).toBeTruthy();
  });

  it('adds and subtracts one piece', () => {
    const onChange = vi.fn();
    render(<PieceCounter pieces={2} productName="Pechuga" onChange={onChange} />);

    fireEvent.click(screen.getByRole('button', { name: 'Sumar unidad de Pechuga' }));
    fireEvent.click(screen.getByRole('button', { name: 'Restar unidad de Pechuga' }));

    expect(onChange.mock.calls).toEqual([[3], [1]]);
  });

  it('cannot go below zero', () => {
    const onChange = vi.fn();
    render(<PieceCounter pieces={0} productName="Pechuga" onChange={onChange} />);

    const minus = screen.getByRole('button', { name: 'Restar unidad de Pechuga' });
    expect((minus as HTMLButtonElement).disabled).toBe(true);
    fireEvent.click(minus);
    expect(onChange).not.toHaveBeenCalled();
  });
});
