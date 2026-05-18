import React, { FormEvent, useCallback, useMemo, useRef, useState } from 'react';
import { marked } from 'marked';
import HTMLReactParser from 'html-react-parser';
import { AppToolbar } from '@shared/ui/toolbar/AppToolbar';
import { businessHttpClient, httpRequestData } from '@shared/api/network';
import toast from '@shared/lib/SharedUtils';
import {
  assistantService,
  type AssistantAttachmentPayload,
  type AssistantChatMessage,
} from './services/assistantService';
import { QuerySuggestionChips } from './components/QuerySuggestionChips';
import './Assistant.scss';

const REGENERATION_TEXT = 'Regeneration...';

type ChatMessage = {
  id: number;
  role: 'user' | 'assistant';
  text: string;
  timestamp: Date;
  attachments?: AssistantAttachmentPayload[];
};

type AssistantProps = {
  showToolbar?: boolean;
};

type SpeechRecognitionLike = {
  lang: string;
  interimResults: boolean;
  maxAlternatives: number;
  onresult: ((event: { results?: ArrayLike<ArrayLike<{ transcript?: string }>>; resultIndex?: number }) => void) | null;
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
          <div className="chat-messagebubble-text">{HTMLReactParser(convertToHtml(message.text))}</div>
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
        {isLastAssistantMessage && message.text !== REGENERATION_TEXT && (
          <div className="bubble-button-container">
            <button type="button" className="app-icon-button" title="Copy" onClick={onCopy} aria-label="Copy">
              <i className={`ph ph-${copied ? 'check' : 'copy'}`} aria-hidden="true" />
            </button>
            <button
              type="button"
              className="app-icon-button"
              title="Regenerate"
              onClick={onRegenerate}
              aria-label="Regenerate"
            >
              <i className="ph ph-arrow-clockwise" aria-hidden="true" />
            </button>
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
    businessHttpClient.post('/api/media', formData),
  );

  return {
    name: file.name,
    mimeType: file.type,
    mediaId: data.id,
    s3Url: data.path,
  };
}

export default function Assistant({ showToolbar = true }: AssistantProps) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [inputText, setInputText] = useState('');
  const [pendingFiles, setPendingFiles] = useState<File[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const messageListRef = useRef<HTMLDivElement | null>(null);

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
    async (message: ChatMessage, currentMessages: ChatMessage[], attachments?: AssistantAttachmentPayload[]) => {
      setIsProcessing(true);
      scrollToBottom();

      try {
        const response = await assistantService.chat(
          [...currentMessages, message].map(messageToHistory),
          attachments,
        );
        setMessages((existing) => [
          ...existing,
          { id: Date.now(), role: 'assistant', text: response.message, timestamp: new Date() },
        ]);
      } catch (error: unknown) {
        const message =
          typeof error === 'object' &&
          error !== null &&
          'response' in error &&
          typeof (error as { response?: { data?: { message?: unknown } } }).response?.data?.message === 'string'
            ? (error as { response: { data: { message: string } } }).response.data.message
            : 'No se pudo obtener respuesta del asistente. Inténtalo nuevamente.';
        toast(message, 'error');
      } finally {
        setIsProcessing(false);
        scrollToBottom();
      }
    },
    [scrollToBottom],
  );

  const sendMessage = useCallback(
    async (text: string) => {
      const trimmed = text.trim();
      if (!trimmed || isProcessing) return;

      setInputText('');
      const filesToUpload = pendingFiles;
      setPendingFiles([]);

      try {
        const attachments = filesToUpload.length > 0 ? await Promise.all(filesToUpload.map(uploadFile)) : undefined;
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

  const onRegenerate = useCallback(async () => {
    if (isProcessing) return;
    const lastAssistantIndex = messages.findLastIndex((message) => message.role === 'assistant');
    if (lastAssistantIndex < 0) return;

    const history = messages.slice(0, lastAssistantIndex);
    const previousText = messages[lastAssistantIndex].text;

    setIsProcessing(true);
    setMessages((existing) =>
      existing.map((message, index) =>
        index === lastAssistantIndex ? { ...message, text: REGENERATION_TEXT } : message,
      ),
    );

    try {
      const response = await assistantService.chat(history.map(messageToHistory));
      setMessages((existing) =>
        existing.map((message, index) =>
          index === lastAssistantIndex ? { ...message, text: response.message } : message,
        ),
      );
    } catch (error: unknown) {
      setMessages((existing) =>
        existing.map((message, index) =>
          index === lastAssistantIndex ? { ...message, text: previousText } : message,
        ),
      );
      const message =
        typeof error === 'object' &&
        error !== null &&
        'response' in error &&
        typeof (error as { response?: { data?: { message?: unknown } } }).response?.data?.message === 'string'
          ? (error as { response: { data: { message: string } } }).response.data.message
          : 'No se pudo regenerar la respuesta.';
      toast(message, 'error');
    } finally {
      setIsProcessing(false);
    }
  }, [isProcessing, messages]);

  const startSpeechRecognition = useCallback(() => {
    const Recognition = ((window as typeof window & { SpeechRecognition?: SpeechRecognitionConstructor }).SpeechRecognition ??
      (window as typeof window & { webkitSpeechRecognition?: SpeechRecognitionConstructor }).webkitSpeechRecognition) as
      | SpeechRecognitionConstructor
      | undefined;
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

  const chat = (
    <div className="chat-container mt-4">
      <div ref={messageListRef} className="chat-message-list" aria-live="polite">
        {messages.length === 0 ? (
          <div className="chat-empty-view-center">
            <QuerySuggestionChips onSuggestionClick={onChipClick} />
          </div>
        ) : (
          messages.map((message) => (
            <AssistantMessageBubble
              key={message.id}
              message={message}
              isLastAssistantMessage={message.id === lastAssistantMessageId}
              onRegenerate={onRegenerate}
            />
          ))
        )}
        {isProcessing && <div className="chat-typing">El asistente está escribiendo...</div>}
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
        <button
          type="button"
          className="app-icon-button"
          aria-label="Adjuntar archivo"
          onClick={() => fileInputRef.current?.click()}
          disabled={isProcessing}
        >
          <i className="ph ph-paperclip" aria-hidden="true" />
        </button>
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
          <button
            type="button"
            className="app-icon-button"
            aria-label="Dictado por voz"
            onClick={startSpeechRecognition}
            disabled={isProcessing}
          >
            <i className="ph ph-microphone" aria-hidden="true" />
          </button>
        )}
        <button type="submit" className="app-icon-button" aria-label="Enviar" disabled={isProcessing || !inputText.trim()}>
          <i className="ph ph-paper-plane-tilt" aria-hidden="true" />
        </button>
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
