import React, { useCallback, useState } from 'react';
import Chat, { type ChatTypes } from 'devextreme-react/chat';
import { loadMessages } from 'devextreme-react/common/core/localization';
import { assistant, CHAT_DISABLED_CLASS, user } from './data';
import Message from './Message';
import { dataSource, useApi } from './useApi';
import { AppToolbar } from '../../shared/ui/toolbar/AppToolbar';
import { QuerySuggestionChips } from './components/QuerySuggestionChips';

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
  const [isTyping, setIsTyping] = useState(false);

  const processAIRequest = useCallback(
    async (message: ChatTypes.Message): Promise<void> => {
      setIsProcessing(true);
      setTypingUsers([assistant]);

      await fetchAIResponse(message);

      setTypingUsers([]);
      setIsProcessing(false);
    },
    [fetchAIResponse],
  );

  const onMessageEntered = useCallback(
    async ({ message, event }: ChatTypes.MessageEnteredEvent): Promise<void> => {
      insertMessage({ id: Date.now(), ...message });
      setIsTyping(false);

      if (!alerts.length) {
        (event?.target as HTMLElement).blur();

        await processAIRequest(message);

        (event?.target as HTMLElement).focus();
      }
    },
    [insertMessage, alerts.length, processAIRequest],
  );

  const onTypingStart = useCallback((): void => {
    setIsTyping(true);
  }, []);

  const onTypingEnd = useCallback((): void => {
    setIsTyping(false);
  }, []);

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

  const chat = (
    <div className="chat-container mt-4">
      <QuerySuggestionChips onSuggestionClick={onChipClick} hidden={isTyping || isProcessing} />
      <Chat
        className={isProcessing ? CHAT_DISABLED_CLASS : ''}
        dataSource={dataSource}
        reloadOnChange={false}
        showAvatar={false}
        showDayHeaders={false}
        user={user}
        height={750}
        width={500}
        onMessageEntered={onMessageEntered}
        onTypingStart={onTypingStart}
        onTypingEnd={onTypingEnd}
        alerts={alerts}
        typingUsers={typingUsers}
        messageRender={messageRender}
      />
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
