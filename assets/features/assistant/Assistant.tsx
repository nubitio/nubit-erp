/* eslint-disable react-hooks/refs -- streaming requests use refs as stable request-scoped state */
import React, { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { marked } from 'marked';
import HTMLReactParser from 'html-react-parser';
import { useLocation } from 'react-router-dom';
import { AppToolbar, IconButton } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import toast from '@shared/lib/SharedUtils';
import {
  assistantService,
  type AssistantAttachmentPayload,
  type AssistantChatMessage,
} from './services/assistantService';
import { ASSISTANT_PREFILL_KEY } from './requestAssistantHelp';
import { QuerySuggestionChips } from './components/QuerySuggestionChips';
import { ActionCard, type ProposedAction } from './components/ActionCard';
import { useAssistantStream } from './hooks/useAssistantStream';
import type { ScreenContext } from './types';
import './Assistant.scss';

const REGENERATION_TEXT = 'Regeneration...';

type ChatMessage = {
  id: number;
  role: 'user' | 'assistant';
  text: string;
  timestamp: Date;
  attachments?: AssistantAttachmentPayload[];
  actions?: ProposedAction[];
};

type AssistantProps = {
  showToolbar?: boolean;
  screen?: ScreenContext['screen'];
  screenExtra?: Record<string, unknown>;
  entityType?: string;
  entityId?: string;
};

type SpeechRecognitionLike = {
  lang: string;
  interimResults: boolean;
  maxAlternatives: number;
  onresult:
    | ((event: {
        results?: ArrayLike<ArrayLike<{ transcript?: string }>>;
        resultIndex?: number;
      }) => void)
    | null;
  onerror: (() => void) | null;
  start: () => void;
};

type SpeechRecognitionConstructor = new () => SpeechRecognitionLike;

const speechSupported =
  typeof window !== 'undefined' &&
  !!(
    (window as typeof window & { SpeechRecognition?: unknown }).SpeechRecognition ||
    (window as typeof window & { webkitSpeechRecognition?: unknown }).webkitSpeechRecognition
  );

function messageToHistory(message: ChatMessage): AssistantChatMessage {
  return {
    role: message.role,
    content: message.text,
  };
}

function convertToHtml(value: string): string {
  return marked(value) as string;
}

function AssistantMessageBubble({
  message,
  isLastAssistantMessage,
  onRegenerate,
}: {
  message: ChatMessage;
  isLastAssistantMessage: boolean;
  onRegenerate: () => void;
}) {
  const [copied, setCopied] = useState(false);

  const onCopy = useCallback(() => {
    navigator.clipboard?.writeText(message.text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2500);
  }, [message.text]);

  return (
    <div className={`chat-message chat-message--${message.role}`}>
      <div className="chat-message__bubble">
        {message.text === REGENERATION_TEXT ? (
          <span>{REGENERATION_TEXT}</span>
        ) : (
          <div className="chat-messagebubble-text">
            {HTMLReactParser(convertToHtml(message.text))}
          </div>
        )}
        {(message.attachments?.length ?? 0) > 0 && (
          <div className="chat-message__attachments">
            {message.attachments?.map((attachment) => (
              <span key={attachment.mediaId} className="chat-message__attachment">
                <i className="ph ph-paperclip" aria-hidden="true" />
                {attachment.name}
              </span>
            ))}
          </div>
        )}
        {message.actions?.map((action) => (
          <ActionCard key={action.id} action={action} />
        ))}
        {isLastAssistantMessage && message.text !== REGENERATION_TEXT && (
          <div className="bubble-button-container">
            <IconButton icon={`ph ph-${copied ? 'check' : 'copy'}`} label="Copy" onClick={onCopy} />
            <IconButton icon="ph ph-arrow-clockwise" label="Regenerate" onClick={onRegenerate} />
          </div>
        )}
      </div>
    </div>
  );
}

async function uploadFile(file: File): Promise<AssistantAttachmentPayload> {
  const formData = new FormData();
  formData.append('file', file);
  const data = await httpRequestData<{ id: string; path: string }>(
    httpClient.post('/api/media', formData),
  );

  return {
    name: file.name,
    mimeType: file.type,
    mediaId: data.id,
    s3Url: data.path,
  };
}

export default function Assistant({
  showToolbar = true,
  screen: screenOverride,
  screenExtra,
  entityType,
  entityId,
}: AssistantProps) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [inputText, setInputText] = useState('');
  const [pendingFiles, setPendingFiles] = useState<File[]>([]);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const messageListRef = useRef<HTMLDivElement | null>(null);

  // Streaming hook — replaces the blocking assistantService.chat call
  const { streamingText, status, stream, abort } = useAssistantStream();
  const isProcessing = status === 'streaming';

  useEffect(() => {
    let cancelled = false;
    void assistantService
      .currentThread()
      .then((thread) => {
        if (cancelled || thread.messages.length === 0) return;
        setMessages(
          thread.messages.map((message, index) => ({
            id: Date.parse(message.createdAt) + index,
            role: message.role,
            text: message.content,
            timestamp: new Date(message.createdAt),
            actions: message.actions ?? undefined,
          })),
        );
      })
      .catch(() => {
        /* fail closed: empty chat */
      });
    return () => {
      cancelled = true;
    };
  }, []);

  // Derive screen context from the current route so the AI knows where the user is
  const { pathname } = useLocation();
  const screenContext = useMemo((): ScreenContext => {
    let screen: ScreenContext['screen'] = pathname.replace(/^\//, '').split('/')[0] ?? 'unknown';
    if (screenOverride) screen = screenOverride;
    else if (pathname.startsWith('/pos')) screen = 'pos';
    else if (pathname.startsWith('/dashboard')) screen = 'dashboard';
    else if (pathname.startsWith('/sale')) screen = 'sale_form';
    else if (pathname.startsWith('/inventory')) screen = 'inventory';
    else if (pathname.startsWith('/cash')) screen = 'cash_register';
    else if (pathname.startsWith('/customer')) screen = 'customers';
    else if (pathname.startsWith('/product')) screen = 'products';
    else if (pathname.startsWith('/purchase')) screen = 'purchases';
    else if (pathname.startsWith('/report')) screen = 'dashboard';

    const entityMatch = pathname.match(/\/([0-9a-fA-F-]{8,}|[0-9]+)(?:\/|$)/);
    return {
      screen,
      entityType: entityType ?? (entityMatch ? screen.replace(/s$/, '') : undefined),
      entityId: entityId ?? entityMatch?.[1],
      extra: screenExtra,
    };
  }, [pathname, screenOverride, screenExtra, entityType, entityId]);

  const lastAssistantMessageId = useMemo(
    () => [...messages].reverse().find((message) => message.role === 'assistant')?.id,
    [messages],
  );

  const scrollToBottom = useCallback(() => {
    window.setTimeout(() => {
      messageListRef.current?.scrollTo({ top: messageListRef.current.scrollHeight });
    });
  }, []);

  const askAssistant = useCallback(
    async (
      message: ChatMessage,
      currentMessages: ChatMessage[],
      attachments?: AssistantAttachmentPayload[],
    ) => {
      scrollToBottom();

      // Placeholder so the user sees the bubble appear immediately.
      // Use Date.now() + 1 to avoid ID collision with the user message that was
      // also stamped with Date.now() milliseconds earlier in sendMessage().
      const placeholderId = Date.now() + 1;
      setMessages((existing) => [
        ...existing,
        { id: placeholderId, role: 'assistant', text: '', timestamp: new Date() },
      ]);

      try {
        const result = await stream(
          [...currentMessages, message].map(messageToHistory),
          attachments,
          screenContext,
        );

        // Replace placeholder with final text once streaming completes
        setMessages((existing) =>
          existing.map((m) =>
            m.id === placeholderId ? { ...m, text: result.text, actions: result.actions } : m,
          ),
        );
      } catch (error: unknown) {
        // Remove placeholder on error
        setMessages((existing) => existing.filter((m) => m.id !== placeholderId));
        const errorMsg =
          error instanceof Error
            ? error.message
            : 'No se pudo obtener respuesta del asistente. Inténtalo nuevamente.';
        toast(errorMsg, 'error');
      } finally {
        scrollToBottom();
      }
    },
    [scrollToBottom, stream, screenContext],
  );

  const sendMessage = useCallback(
    async (text: string) => {
      const trimmed = text.trim();
      if (!trimmed || isProcessing) return;

      setInputText('');
      const filesToUpload = pendingFiles;
      setPendingFiles([]);

      try {
        const attachments =
          filesToUpload.length > 0 ? await Promise.all(filesToUpload.map(uploadFile)) : undefined;
        const userMessage: ChatMessage = {
          id: Date.now(),
          role: 'user',
          text: trimmed,
          timestamp: new Date(),
          attachments,
        };
        const currentMessages = messages;
        setMessages((existing) => [...existing, userMessage]);
        await askAssistant(userMessage, currentMessages, attachments);
      } catch {
        toast('No se pudo subir el archivo', 'error');
        setPendingFiles(filesToUpload);
      }
    },
    [askAssistant, isProcessing, messages, pendingFiles],
  );

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    void sendMessage(inputText);
  };

  const onChipClick = useCallback(
    (query: string) => {
      void sendMessage(query);
    },
    [sendMessage],
  );

  const sendMessageRef = useRef(sendMessage);
  sendMessageRef.current = sendMessage;

  useEffect(() => {
    const consume = (query: string) => {
      const trimmed = query.trim();
      if (trimmed === '') return;
      void sendMessageRef.current(trimmed);
    };

    const stored = sessionStorage.getItem(ASSISTANT_PREFILL_KEY);
    if (stored) {
      sessionStorage.removeItem(ASSISTANT_PREFILL_KEY);
      consume(stored);
    }

    const onPrefill = (event: Event) => {
      const query = (event as CustomEvent<{ query?: string }>).detail?.query;
      if (typeof query === 'string') {
        sessionStorage.removeItem(ASSISTANT_PREFILL_KEY);
        consume(query);
      }
    };
    window.addEventListener('efact:assistant-prefill', onPrefill);
    return () => window.removeEventListener('efact:assistant-prefill', onPrefill);
  }, []);

  const onRegenerate = useCallback(async () => {
    if (isProcessing) return;
    const lastAssistantIndex = messages.findLastIndex((message) => message.role === 'assistant');
    if (lastAssistantIndex < 0) return;

    const history = messages.slice(0, lastAssistantIndex);
    const previousText = messages[lastAssistantIndex].text;

    // Clear the previous assistant text so streaming text shows from empty
    setMessages((existing) =>
      existing.map((message, index) =>
        index === lastAssistantIndex ? { ...message, text: '' } : message,
      ),
    );

    try {
      const result = await stream(history.map(messageToHistory), undefined, screenContext);
      setMessages((existing) =>
        existing.map((message, index) =>
          index === lastAssistantIndex
            ? { ...message, text: result.text, actions: result.actions }
            : message,
        ),
      );
    } catch (error: unknown) {
      setMessages((existing) =>
        existing.map((message, index) =>
          index === lastAssistantIndex ? { ...message, text: previousText } : message,
        ),
      );
      const errorMsg =
        error instanceof Error ? error.message : 'No se pudo regenerar la respuesta.';
      toast(errorMsg, 'error');
    }
  }, [isProcessing, messages, stream, screenContext]);

  const startSpeechRecognition = useCallback(() => {
    const Recognition = ((
      window as typeof window & { SpeechRecognition?: SpeechRecognitionConstructor }
    ).SpeechRecognition ??
      (window as typeof window & { webkitSpeechRecognition?: SpeechRecognitionConstructor })
        .webkitSpeechRecognition) as SpeechRecognitionConstructor | undefined;
    if (!Recognition) return;

    const recognition = new Recognition();
    recognition.lang = 'es-PE';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    recognition.onresult = (event) => {
      const transcript = event.results?.[event.resultIndex ?? 0]?.[0]?.transcript ?? '';
      if (transcript) setInputText(transcript);
    };
    recognition.onerror = () => toast('No se pudo iniciar el dictado', 'error');
    recognition.start();
  }, []);

  const startNewConversation = useCallback(() => {
    abort();
    setMessages([]);
    setInputText('');
    void assistantService.startNewThread().catch(() => {
      toast('No se pudo iniciar una conversación nueva.', 'error');
    });
  }, [abort]);

  const chat = (
    <div className="chat-container mt-4">
      <div className="chat-thread-bar">
        <button type="button" className="chat-thread-bar__new" onClick={startNewConversation}>
          Nueva conversación
        </button>
      </div>
      <div ref={messageListRef} className="chat-message-list" aria-live="polite">
        {messages.length === 0 ? (
          <div className="chat-empty-view-center">
            <QuerySuggestionChips onSuggestionClick={onChipClick} />
          </div>
        ) : (
          messages.map((message) => {
            // While streaming, the last assistant bubble shows the live delta
            const displayText =
              isProcessing &&
              message.role === 'assistant' &&
              message.id === lastAssistantMessageId &&
              streamingText
                ? streamingText
                : message.text;
            return (
              <AssistantMessageBubble
                key={message.id}
                message={{ ...message, text: displayText }}
                isLastAssistantMessage={message.id === lastAssistantMessageId}
                onRegenerate={onRegenerate}
              />
            );
          })
        )}
        {isProcessing && !streamingText && (
          <div className="chat-typing">El asistente está escribiendo...</div>
        )}
      </div>

      {pendingFiles.length > 0 && (
        <div className="chat-pending-files">
          {pendingFiles.map((file) => (
            <span key={`${file.name}-${file.size}`} className="chat-pending-file">
              <i className="ph ph-paperclip" aria-hidden="true" />
              {file.name}
            </span>
          ))}
        </div>
      )}

      <form className="chat-messagebox" onSubmit={onSubmit}>
        <IconButton
          icon="ph ph-paperclip"
          label="Adjuntar archivo"
          onClick={() => fileInputRef.current?.click()}
          disabled={isProcessing}
        />
        <input
          ref={fileInputRef}
          type="file"
          multiple
          hidden
          accept=".jpg,.jpeg,.png,.webp,.pdf"
          onChange={(event) => {
            setPendingFiles(Array.from(event.target.files ?? []));
            event.target.value = '';
          }}
        />
        <textarea
          value={inputText}
          placeholder="Pregunta al Asistente de IA..."
          rows={1}
          disabled={isProcessing}
          onChange={(event) => setInputText(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
              event.preventDefault();
              void sendMessage(inputText);
            }
          }}
        />
        {speechSupported && (
          <IconButton
            icon="ph ph-microphone"
            label="Dictado por voz"
            onClick={startSpeechRecognition}
            disabled={isProcessing}
          />
        )}
        <IconButton
          type="submit"
          icon="ph ph-paper-plane-tilt"
          label="Enviar"
          disabled={isProcessing || !inputText.trim()}
        />
      </form>
    </div>
  );

  if (!showToolbar) {
    return <div className="assistant-panel">{chat}</div>;
  }

  return (
    <AppToolbar title="Asistente de IA" icon="ph ph-sparkle" showRefreshButton={false}>
      <div className="assistant-panel">{chat}</div>
    </AppToolbar>
  );
}
