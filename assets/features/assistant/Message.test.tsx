// @vitest-environment jsdom
/**
 * Unit tests for SLICE-013 — Message component
 *
 * Coverage:
 *   - Renders markdown text as HTML (bold, etc.)
 *   - Shows REGENERATION_TEXT placeholder when text === REGENERATION_TEXT
 *   - Copy button changes icon from 'copy' to 'check' on click
 *   - Copy icon reverts to 'copy' after 2500ms
 *   - navigator.clipboard.writeText is called with the correct text
 *   - onRegenerateButtonClick is called when regenerate button is clicked
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';

// ---------------------------------------------------------------------------
// Module-level mocks
// ---------------------------------------------------------------------------

// Mock DevExtreme Button to a simple HTML button.
// The testid is keyed on hint (e.g. "Copy" → "dx-button-copy", "Regenerate" → "dx-button-regenerate").
// The data-icon attribute reflects the current icon prop so we can assert icon changes.
vi.mock('devextreme-react/button', () => ({
  Button: ({ icon, hint, onClick }: any) =>
    React.createElement('button', {
      'data-testid': `dx-button-${hint?.toLowerCase() ?? icon}`,
      'data-icon': icon,
      onClick,
    }),
}));

// ---------------------------------------------------------------------------
// Imports — after mocks
// ---------------------------------------------------------------------------

import Message from './Message';
import { REGENERATION_TEXT } from './data';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const noop = () => {};

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Message', () => {
  beforeEach(() => {
    // Mock clipboard API
    Object.defineProperty(navigator, 'clipboard', {
      value: { writeText: vi.fn().mockResolvedValue(undefined) },
      writable: true,
      configurable: true,
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('REGENERATION_TEXT placeholder', () => {
    it('renders a span with REGENERATION_TEXT when text equals REGENERATION_TEXT', () => {
      const { container } = render(
        React.createElement(Message, { text: REGENERATION_TEXT, onRegenerateButtonClick: noop }),
      );
      const span = container.querySelector('span');
      expect(span).not.toBeNull();
      expect(span?.textContent).toBe(REGENERATION_TEXT);
    });

    it('does not render the copy or regenerate buttons when text is REGENERATION_TEXT', () => {
      render(
        React.createElement(Message, { text: REGENERATION_TEXT, onRegenerateButtonClick: noop }),
      );
      expect(screen.queryByTestId('dx-button-copy')).toBeNull();
      expect(screen.queryByTestId('dx-button-regenerate')).toBeNull();
    });
  });

  describe('Markdown rendering', () => {
    it('converts **bold** markdown to <strong> HTML element', () => {
      const { container } = render(
        React.createElement(Message, {
          text: '**bold text**',
          onRegenerateButtonClick: noop,
        }),
      );
      const strong = container.querySelector('strong');
      expect(strong).not.toBeNull();
      expect(strong?.textContent).toBe('bold text');
    });

    it('renders plain text without extra markup', () => {
      const { container } = render(
        React.createElement(Message, {
          text: 'Hello world',
          onRegenerateButtonClick: noop,
        }),
      );
      const bubble = container.querySelector('.chat-messagebubble-text');
      expect(bubble).not.toBeNull();
      expect(bubble?.textContent).toContain('Hello world');
    });

    it('renders the message bubble container div', () => {
      const { container } = render(
        React.createElement(Message, {
          text: 'Some text',
          onRegenerateButtonClick: noop,
        }),
      );
      expect(container.querySelector('.chat-messagebubble-text')).not.toBeNull();
      expect(container.querySelector('.bubble-button-container')).not.toBeNull();
    });
  });

  describe('Copy button', () => {
    it('renders with icon="copy" initially', () => {
      render(React.createElement(Message, { text: 'Copy me', onRegenerateButtonClick: noop }));
      const copyBtn = screen.getByTestId('dx-button-copy');
      expect(copyBtn.getAttribute('data-icon')).toBe('copy');
    });

    it('changes data-icon to "check" after clicking copy', () => {
      render(React.createElement(Message, { text: 'Copy me', onRegenerateButtonClick: noop }));
      const copyBtn = screen.getByTestId('dx-button-copy');
      fireEvent.click(copyBtn);
      // The testid stays 'dx-button-copy' (keyed on hint="Copy"), but data-icon changes
      expect(copyBtn.getAttribute('data-icon')).toBe('check');
    });

    it('calls navigator.clipboard.writeText with the message text', () => {
      const text = 'Text to copy';
      render(React.createElement(Message, { text, onRegenerateButtonClick: noop }));
      const copyBtn = screen.getByTestId('dx-button-copy');
      fireEvent.click(copyBtn);
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith(text);
    });

    it('reverts data-icon back to "copy" after 2500ms', () => {
      vi.useFakeTimers();

      render(React.createElement(Message, { text: 'Copy me', onRegenerateButtonClick: noop }));

      const copyBtn = screen.getByTestId('dx-button-copy');
      fireEvent.click(copyBtn);

      // Icon should be 'check' now
      expect(copyBtn.getAttribute('data-icon')).toBe('check');

      // Advance timers by 2500ms
      act(() => {
        vi.advanceTimersByTime(2500);
      });

      // Icon should revert to 'copy'
      expect(copyBtn.getAttribute('data-icon')).toBe('copy');

      vi.useRealTimers();
    });

    it('does not revert icon before 2500ms have elapsed', () => {
      vi.useFakeTimers();

      render(React.createElement(Message, { text: 'Copy me', onRegenerateButtonClick: noop }));

      const copyBtn = screen.getByTestId('dx-button-copy');
      fireEvent.click(copyBtn);

      act(() => {
        vi.advanceTimersByTime(2499);
      });

      // Still showing 'check' (not yet reverted)
      expect(copyBtn.getAttribute('data-icon')).toBe('check');

      vi.useRealTimers();
    });
  });

  describe('Regenerate button', () => {
    it('calls onRegenerateButtonClick when regenerate button is clicked', () => {
      const onRegenerate = vi.fn();
      render(
        React.createElement(Message, {
          text: 'Some response',
          onRegenerateButtonClick: onRegenerate,
        }),
      );
      const regenBtn = screen.getByTestId('dx-button-regenerate');
      fireEvent.click(regenBtn);
      expect(onRegenerate).toHaveBeenCalledTimes(1);
    });

    it('renders the regenerate button with icon="refresh"', () => {
      render(React.createElement(Message, { text: 'Some text', onRegenerateButtonClick: noop }));
      const regenBtn = screen.getByTestId('dx-button-regenerate');
      expect(regenBtn.getAttribute('data-icon')).toBe('refresh');
    });
  });
});
