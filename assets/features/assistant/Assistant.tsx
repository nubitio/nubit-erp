import React, {useCallback, useState} from 'react';
import Chat, {type ChatTypes} from 'devextreme-react/chat';
import {loadMessages} from 'devextreme-react/common/core/localization';
import {assistant, CHAT_DISABLED_CLASS, user} from './data';
import Message from './Message';
import {dataSource, useApi} from './useApi';
import {AppToolbar} from '../../components/utils/toolbar/AppToolbar';

loadMessages({
  es: {
    'dxChat-emptyListMessage': 'El chat está vacío',
    'dxChat-emptyListPrompt': 'El Asistente de IA está listo para responder tus preguntas.',
    'dxChat-textareaPlaceholder': 'Pregunta al Asistente de IA...',
  }
});

type AssistantProps = {
  showToolbar?: boolean;
};

export default function Assistant({showToolbar = true}: AssistantProps) {
  const {alerts, insertMessage, fetchAIResponse, regenerateLastAIResponse} = useApi();

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
    async ({message, event}: ChatTypes.MessageEnteredEvent): Promise<void> => {
      insertMessage({id: Date.now(), ...message});

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
    ({message}: { message: ChatTypes.Message }) => (
      <Message text={message.text} onRegenerateButtonClick={onRegenerateButtonClick}/>
    ),
    [onRegenerateButtonClick],
  );

  const chat = (
    <div className="chat-container mt-4">
      <Chat
        className={isProcessing ? CHAT_DISABLED_CLASS : ''}
        dataSource={dataSource}
        reloadOnChange={false}
        showAvatar={false}
        showDayHeaders={false}
        user={user}
        height={580}
        onMessageEntered={onMessageEntered}
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
