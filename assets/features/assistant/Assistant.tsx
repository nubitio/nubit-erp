import React, { useCallback, useRef, useState } from 'react';
import Chat, { type ChatTypes, type ChatRef } from 'devextreme-react/chat';
import SpeechToText, { SpeechRecognitionConfig } from 'devextreme-react/speech-to-text';
import type { SpeechToTextTypes } from 'devextreme-react/speech-to-text';
import { loadMessages } from 'devextreme-react/common/core/localization';
import './Assistant.scss';
import { assistant, CHAT_DISABLED_CLASS, user } from './data';
import Message from './Message';
import { dataSource, useApi } from './useApi';
import { AppToolbar } from '../../shared/ui/toolbar/AppToolbar';
import { QuerySuggestionChips } from './components/QuerySuggestionChips';
import type { AssistantAttachmentPayload } from './services/assistantService';
import toast from '@shared/lib/SharedUtils';
// Note: DevExtreme 25.2 introduces LoadPanel indicatorOptions={{ animationType: "sparkle" }}
// and LoadIndicator animationType="sparkle". When a LoadPanel or LoadIndicator is added to
// this component to show AI generation progress, set animationType="sparkle" on it.

/**
 * AC-06: Detect Web Speech API support at module load time.
 * The SpeechToText mic button is only rendered when the browser supports it.
 */
const speechSupported =
  typeof window !== 'undefined' &&
  !!(
    (window as typeof window & { SpeechRecognition?: unknown }).SpeechRecognition ||
    (window as typeof window & { webkitSpeechRecognition?: unknown }).webkitSpeechRecognition
  );

loadMessages({
  es: {
    'dxChat-emptyListMessage': 'El chat está vacío',
    'dxChat-emptyListPrompt': 'El Asistente de IA está listo para responder tus preguntas.',
    'dxChat-textareaPlaceholder': 'Pregunta al Asistente de IA...',
  },
});

type AssistantProps = {
  showToolbar?: boolean;
};

export default function Assistant({ showToolbar = true }: AssistantProps) {
  const { alerts, insertMessage, fetchAIResponse, regenerateLastAIResponse } = useApi();

  const [typingUsers, setTypingUsers] = useState<ChatTypes.User[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);

  /**
   * Ref to bridge onUploaded → onMessageEntered.
   * Stores mediaId + s3Url keyed by file name for enriching DX Chat attachments.
   * The JWT auth token is stored in an HTTP-only cookie (AUTH_TOKEN) by the backend,
   * so browser XHR automatically includes it for same-origin requests — no Authorization
   * header is required.
   */
  const uploadedFilesRef = useRef<
    Map<string, { mediaId: string; mimeType: string; s3Url: string }>
  >(new Map());

  /** Ref to the DX Chat instance — used to programmatically set the input text from speech. */
  const chatRef = useRef<ChatRef>(null);

  /**
   * AC-05: Handle speech recognition result.
   * Extracts the transcript from the native SpeechRecognitionEvent and injects
   * it into the Chat's input textarea via the DX internal message box option.
   */
  const onSpeechResult = useCallback((e: SpeechToTextTypes.ResultEvent): void => {
    // The `event` property is the native SpeechRecognitionEvent from the Web Speech API.
    // TypeScript's lib.dom.d.ts does not declare SpeechRecognitionEvent, so we cast it.
    const nativeEvent = e.event as Event & {
      results?: SpeechRecognitionResultList;
      resultIndex?: number;
    };

    if (!nativeEvent.results || !nativeEvent.results.length) {
      return;
    }

    const resultIndex = nativeEvent.resultIndex ?? 0;
    const transcript = nativeEvent.results[resultIndex]?.[0]?.transcript ?? '';

    if (!transcript) {
      return;
    }

    // Inject the recognized text into the Chat's internal message box.
    // dxChat exposes a `_messageBox` field (internal API) that holds the DX TextArea.
    const chatInstance = chatRef.current?.instance();
    if (chatInstance) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (chatInstance as any)._messageBox?.option('text', transcript);
    }
  }, []);

  const processAIRequest = useCallback(
    async (
      message: ChatTypes.Message,
      attachments?: AssistantAttachmentPayload[],
    ): Promise<void> => {
      setIsProcessing(true);
      setTypingUsers([assistant]);

      await fetchAIResponse(message, attachments);

      setTypingUsers([]);
      setIsProcessing(false);
    },
    [fetchAIResponse],
  );

  const onMessageEntered = useCallback(
    async ({ message, event }: ChatTypes.MessageEnteredEvent): Promise<void> => {
      // Enrich DX attachments (which only have name+size) with mediaId+s3Url from ref.
      const enrichedAttachments: AssistantAttachmentPayload[] = (
        (message.attachments ?? []) as Array<{ name?: string; type?: string }>
      )
        .map((att) => {
          const uploaded = att.name ? uploadedFilesRef.current.get(att.name) : undefined;
          return uploaded
            ? {
                name: att.name ?? '',
                mimeType: uploaded.mimeType,
                mediaId: uploaded.mediaId,
                s3Url: uploaded.s3Url,
              }
            : null;
        })
        .filter((item): item is AssistantAttachmentPayload => item !== null);

      uploadedFilesRef.current.clear();

      const attachmentsToSend = enrichedAttachments.length > 0 ? enrichedAttachments : undefined;

      // Insert message into the data source.
      insertMessage({ id: Date.now(), ...message });

      if (!alerts.length) {
        (event?.target as HTMLElement).blur();

        await processAIRequest(message, attachmentsToSend);

        (event?.target as HTMLElement).focus();
      }
    },
    [insertMessage, alerts.length, processAIRequest],
  );

  const onChipClick = useCallback(
    async (query: string): Promise<void> => {
      const message: ChatTypes.Message = {
        text: query,
        timestamp: new Date(),
        author: user,
      };
      insertMessage({ id: Date.now(), ...message });

      if (!alerts.length) {
        await processAIRequest(message);
      }
    },
    [insertMessage, alerts.length, processAIRequest],
  );

  const onRegenerateButtonClick = useCallback(async (): Promise<void> => {
    setIsProcessing(true);

    await regenerateLastAIResponse();

    setIsProcessing(false);
  }, [regenerateLastAIResponse]);

  const messageRender = useCallback(
    ({ message }: { message: ChatTypes.Message }) => (
      <Message text={message.text} onRegenerateButtonClick={onRegenerateButtonClick} />
    ),
    [onRegenerateButtonClick],
  );

  const emptyViewRender = useCallback(
    () => (
      <div className="chat-empty-view-center">
        <QuerySuggestionChips onSuggestionClick={onChipClick} />
      </div>
    ),
    [onChipClick],
  );

  const chat = (
    <div className="chat-container mt-4">
      <Chat
        ref={chatRef}
        className={isProcessing ? CHAT_DISABLED_CLASS : ''}
        dataSource={dataSource}
        reloadOnChange={false}
        showAvatar={false}
        showDayHeaders={false}
        user={user}
        height={560}
        onMessageEntered={onMessageEntered}
        alerts={alerts}
        typingUsers={typingUsers}
        messageRender={messageRender}
        emptyViewRender={emptyViewRender}
        fileUploaderOptions={{
          allowedFileExtensions: ['.jpg', '.jpeg', '.png', '.webp', '.pdf'],
          maxFileSize: 10 * 1024 * 1024, // 10 MB
          uploadFile: async (file: File): Promise<void> => {
            const formData = new FormData();
            formData.append('file', file);
            const response = await fetch('/api/media', { method: 'POST', body: formData });
            if (!response.ok) {
              throw new Error(`Upload failed: ${response.status} ${response.statusText}`);
            }
            const data = (await response.json()) as { id: string; path: string };
            uploadedFilesRef.current.set(file.name, {
              mediaId: data.id,
              mimeType: file.type,
              s3Url: data.path,
            });
          },
          onUploadError: (_e: unknown) => {
            toast('No se pudo subir el archivo', 'error');
          },
        }}
      />
      <div className="chat-toolbar-row">
        {speechSupported && (
          <div className="chat-speech-to-text-wrapper" aria-label="Dictado por voz">
            <SpeechToText hint="Dictar mensaje" onResult={onSpeechResult}>
              <SpeechRecognitionConfig lang="es-PE" />
            </SpeechToText>
          </div>
        )}
      </div>
    </div>
  );

  if (!showToolbar) {
    return <div className="assistant-panel">{chat}</div>;
  }

  return (
    <AppToolbar title="Asistente" showRefreshButton={false}>
      {chat}
    </AppToolbar>
  );
}
