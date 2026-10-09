import { useCallback, useRef, useState } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { exceptionalFetch } from '@shared/api/network';
import type {
  AssistantChatMessage,
  AssistantAttachmentPayload,
} from '../services/assistantService';
import type { ProposedAction } from '../components/ActionCard';
import type { ScreenContext } from '../types';

const API = getCoreApiBaseUrl();

export type StreamStatus = 'idle' | 'streaming' | 'done' | 'error';

export type AssistantStreamResult = {
  text: string;
  actions: ProposedAction[];
};

export type UseAssistantStreamReturn = {
  /** Accumulated text delta as it streams in. Empty when idle. */
  streamingText: string;
  status: StreamStatus;
  /** Start a streaming request. Resolves with the full response text when done. */
  stream: (
    messages: AssistantChatMessage[],
    attachments?: AssistantAttachmentPayload[],
    screenContext?: ScreenContext,
  ) => Promise<AssistantStreamResult>;
  /** Abort an in-flight request. */
  abort: () => void;
};

/**
 * Streams the AI assistant response via Server-Sent Events.
 *
 * Connects to POST /api/assistant/stream and exposes incremental delta tokens
 * so the UI can render text as it arrives, eliminating the 5-25s spinner wait.
 *
 * SSE protocol (from AssistantStreamController):
 *   data: {"delta": "token text"}\n\n
 *   data: [DONE]\n\n
 *   data: {"error": "message"}\n\n
 */
export function useAssistantStream(): UseAssistantStreamReturn {
  const [streamingText, setStreamingText] = useState('');
  const [status, setStatus] = useState<StreamStatus>('idle');
  const abortRef = useRef<AbortController | null>(null);

  const abort = useCallback(() => {
    abortRef.current?.abort();
    abortRef.current = null;
    setStatus('idle');
    setStreamingText('');
  }, []);

  const stream = useCallback(
    async (
      messages: AssistantChatMessage[],
      attachments?: AssistantAttachmentPayload[],
      screenContext?: ScreenContext,
    ): Promise<AssistantStreamResult> => {
      // Abort any previous in-flight request
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      setStreamingText('');
      setStatus('streaming');

      const body: Record<string, unknown> = { messages };
      if (attachments && attachments.length > 0) body.attachments = attachments;
      if (screenContext) body.screenContext = screenContext;

      let accumulated = '';
      const actions: ProposedAction[] = [];

      try {
        // SSE streaming requires the native fetch ReadableStream API.
        const response = await exceptionalFetch(
          `${API}assistant/stream`,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            signal: controller.signal,
            credentials: 'include', // forward HttpOnly cookie (JWT)
          },
          'browser-native-api',
        );

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const reader = response.body?.getReader();
        if (!reader) throw new Error('ReadableStream not available');

        const decoder = new TextDecoder();

        for (;;) {
          const { done, value } = await reader.read();
          if (done) break;

          const chunk = decoder.decode(value, { stream: true });
          const lines = chunk.split('\n');

          for (const line of lines) {
            if (!line.startsWith('data: ')) continue;

            const raw = line.slice(6).trim();

            if (raw === '[DONE]') {
              setStatus('done');
              return { text: accumulated, actions };
            }

            try {
              const parsed = JSON.parse(raw) as {
                delta?: string;
                error?: string;
                id?: string;
                kind?: string;
                summary?: string;
                payload?: Record<string, unknown>;
                expiresAt?: string;
              };

              if (parsed.error) {
                setStatus('error');
                throw new Error(parsed.error);
              }

              if (parsed.id && parsed.kind && parsed.summary) {
                actions.push({
                  id: parsed.id,
                  kind: parsed.kind,
                  summary: parsed.summary,
                  payload: parsed.payload,
                  expiresAt: parsed.expiresAt,
                });
                continue;
              }

              if (parsed.delta) {
                accumulated += parsed.delta;
                setStreamingText(accumulated);
              }
            } catch (parseError) {
              // Skip malformed SSE lines
              if (parseError instanceof SyntaxError) continue;
              throw parseError;
            }
          }
        }

        setStatus('done');
        return { text: accumulated, actions };
      } catch (error) {
        if ((error as { name?: string }).name === 'AbortError') {
          setStatus('idle');
          return { text: accumulated, actions };
        }
        setStatus('error');
        setStreamingText('');
        throw error;
      } finally {
        if (abortRef.current === controller) {
          abortRef.current = null;
        }
      }
    },
    [],
  );

  return { streamingText, status, stream, abort };
}
