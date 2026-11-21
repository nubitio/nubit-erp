import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { UserAvatar } from './UserAvatar';

afterEach(cleanup);

describe('UserAvatar', () => {
  it('shows the photo when there is one', () => {
    render(<UserAvatar name="Ana Pérez" avatarUrl="/api/profile/avatar?v=1" size={40} />);
    const photo = screen.getByRole('img', { name: 'Ana Pérez' });
    expect(photo.getAttribute('src')).toBe('/api/profile/avatar?v=1');
  });

  it('falls back to initials without a photo', () => {
    render(<UserAvatar name="Ana Pérez" avatarUrl={null} />);
    expect(screen.queryByRole('img', { name: 'Ana Pérez' })?.tagName).not.toBe('IMG');
    expect(document.querySelector('img')).toBeNull();
  });

  it('falls back to initials when the photo fails to load, and retries on a new URL', () => {
    const { rerender } = render(<UserAvatar name="Ana Pérez" avatarUrl="/broken.png" />);
    fireEvent.error(document.querySelector('img') as HTMLImageElement);
    expect(document.querySelector('img')).toBeNull();

    rerender(<UserAvatar name="Ana Pérez" avatarUrl="/fixed.png" />);
    expect(document.querySelector('img')?.getAttribute('src')).toBe('/fixed.png');
  });
});
