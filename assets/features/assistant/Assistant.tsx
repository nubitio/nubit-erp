import React, { useCallback, useState } from 'react';
import Chat, { type ChatTypes } from 'devextreme-react/chat';
import { loadMessages } from 'devextreme-react/common/core/localization';
import { user, assistant, CHAT_DISABLED_CLASS } from './data';
import Message from './Message';
import { dataSource, useApi } from './useApi';
import { AppToolbar } from '../../components/utils/toolbar/AppToolbar';

loadMessages({
  en: {
    'dxChat-emptyListMessage': 'Chat is Empty',
    'dxChat-emptyListPrompt': 'AI Assistant is ready to answer your questions.',
    'dxChat-textareaPlaceholder': 'Ask AI Assistant...',
  },
});

export default function Assistant() {
  const { alerts, insertMessage, fetchAIResponse, regenerateLastAIResponse } = useApi();

  const [typingUsers, setTypingUsers] = useState<ChatTypes.User[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);

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

      if (!alerts.length) {
        (event?.target as HTMLElement).blur();

        await processAIRequest(message);

        (event?.target as HTMLElement).focus();
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

  return (
    <AppToolbar title="Asistente" showRefreshButton={false}>
      <div className="chat-container mt-6">
        <Chat
          className={isProcessing ? CHAT_DISABLED_CLASS : ''}
          dataSource={dataSource}
          reloadOnChange={false}
          showAvatar={false}
          showDayHeaders={false}
          user={user}
          height={500}
          onMessageEntered={onMessageEntered}
          alerts={alerts}
          typingUsers={typingUsers}
          messageRender={messageRender}
        />
      </div>
    </AppToolbar>
  );
}
