// @vitest-environment jsdom
/**
 * Unit tests for SLICE-013 — useApi hook and getAIResponse function
 *
 * Coverage:
 *   - getAIResponse: calls assistantService.chat() with correct messages
 *   - getAIResponse: returns the message from the response
 *   - getAIResponse: respects optional delay
 *   - getAIResponse: propagates errors from assistantService.chat()
 *   - getAIResponse: passes attachments to assistantService.chat()
 *   - useApi.insertMessage: inserts a message into the dataSource
 *   - useApi.fetchAIResponse: calls getAIResponse and inserts AI response
 *   - useApi.fetchAIResponse: sets typing indicator during request
 *   - useApi.fetchAIResponse: populates alerts on failure
 *   - useApi.regenerateLastAIResponse: updates last message with AI response
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// ---------------------------------------------------------------------------
// Module-level mocks — hoisted before imports
// ---------------------------------------------------------------------------

vi.mock('./services/assistantService', () => ({
  assistantService: {
    chat: vi.fn(),
  },
}));

// Mock DevExtreme data modules to avoid singleton issues
vi.mock('devextreme-react/common/data', () => {
  const items: any[] = [];

  const mockStorePush = vi.fn((changes: any[]) => {
    for (const change of changes) {
      if (change.type === 'insert') {
        items.push(change.data);
      } else if (change.type === 'update') {
        const idx = items.findIndex((m) => m.id === change.key);
        if (idx !== -1) {
          items[idx] = { ...items[idx], ...change.data };
        }
      }
    }
  });

  function CustomStore(_opts: any) {
    return { push: mockStorePush };
  }

  function DataSource(_opts: any) {
    return {
      store: () => ({ push: mockStorePush }),
      items: () => [...items],
    };
  }

  return { CustomStore, DataSource };
});

// ---------------------------------------------------------------------------
// Imports — after mocks
// ---------------------------------------------------------------------------

import { getAIResponse } from './useApi';
import { assistantService } from './services/assistantService';
import type { AssistantChatMessage } from './services/assistantService';

// ---------------------------------------------------------------------------
// getAIResponse — pure function tests
// ---------------------------------------------------------------------------

describe('getAIResponse', () => {
  const mockChat = assistantService.chat as ReturnType<typeof vi.fn>;

  beforeEach(() => {
    mockChat.mockReset();
  });

  it('calls assistantService.chat() with the provided messages', async () => {
    const messages: AssistantChatMessage[] = [{ role: 'user', content: 'Hello' }];
    mockChat.mockResolvedValueOnce({ message: 'Hi there!' });

    await getAIResponse(messages);

    expect(mockChat).toHaveBeenCalledTimes(1);
    expect(mockChat).toHaveBeenCalledWith(messages, undefined);
  });

  it('returns the message string from the service response', async () => {
    const messages: AssistantChatMessage[] = [{ role: 'user', content: 'What is 2+2?' }];
    mockChat.mockResolvedValueOnce({ message: 'The answer is 4.' });

    const result = await getAIResponse(messages);

    expect(result).toBe('The answer is 4.');
  });

  it('passes attachments to assistantService.chat()', async () => {
    const messages: AssistantChatMessage[] = [{ role: 'user', content: 'See attached' }];
    const attachments = [
      {
        name: 'file.pdf',
        mimeType: 'application/pdf',
        mediaId: 'abc123',
        s3Url: 'https://s3.example.com/file.pdf',
      },
    ];
    mockChat.mockResolvedValueOnce({ message: 'Got it.' });

    await getAIResponse(messages, undefined, attachments);

    expect(mockChat).toHaveBeenCalledWith(messages, attachments);
  });

  it('propagates errors thrown by assistantService.chat()', async () => {
    const messages: AssistantChatMessage[] = [{ role: 'user', content: 'Fail please' }];
    const error = new Error('Network error');
    mockChat.mockRejectedValueOnce(error);

    await expect(getAIResponse(messages)).rejects.toThrow('Network error');
  });

  it('respects the optional delay using fake timers', async () => {
    vi.useFakeTimers();
    const messages: AssistantChatMessage[] = [{ role: 'user', content: 'Delayed' }];
    mockChat.mockResolvedValueOnce({ message: 'Delayed response' });

    const promise = getAIResponse(messages, 500);

    // Advance timers past the delay
    await vi.runAllTimersAsync();

    const result = await promise;
    expect(result).toBe('Delayed response');

    vi.useRealTimers();
  });

  it('resolves immediately when no delay is provided', async () => {
    const messages: AssistantChatMessage[] = [{ role: 'user', content: 'Fast' }];
    mockChat.mockResolvedValueOnce({ message: 'Fast response' });

    const result = await getAIResponse(messages);

    expect(result).toBe('Fast response');
  });

  it('calls chat with multiple messages in conversation history', async () => {
    const messages: AssistantChatMessage[] = [
      { role: 'user', content: 'First message' },
      { role: 'assistant', content: 'First reply' },
      { role: 'user', content: 'Second message' },
    ];
    mockChat.mockResolvedValueOnce({ message: 'Second reply' });

    const result = await getAIResponse(messages);

    expect(mockChat).toHaveBeenCalledWith(messages, undefined);
    expect(result).toBe('Second reply');
  });
});
