import { useCallback, useState } from 'react';
import { type ChatTypes } from 'devextreme-react/chat';
import { CustomStore, DataSource } from 'devextreme-react/common/data';
import { ALERT_TIMEOUT, assistant, REGENERATION_TEXT } from './data';
import { assistantService, type AssistantChatMessage } from '../../features/assistant';

const wait = (delay: number): Promise<void> =>
  new Promise((resolve) => {
    setTimeout(resolve, delay);
  });

export async function getAIResponse(
  messages: AssistantChatMessage[],
  delay?: number,
): Promise<string> {
  const response = await assistantService.chat(messages);

  if (delay) {
    await wait(delay);
  }

  return response.message;
}

const store: ChatTypes.Message[] = [];

const customStore = new CustomStore({
  key: 'id',
  load: (): Promise<ChatTypes.Message[]> =>
    new Promise((resolve) => {
      setTimeout(() => {
        resolve([...store]);
      }, 0);
    }),
  insert: (message: ChatTypes.Message): Promise<ChatTypes.Message> =>
    new Promise((resolve) => {
      setTimeout(() => {
        store.push(message);
        resolve(message);
      });
    }),
});

export const dataSource = new DataSource({
  store: customStore,
  paginate: false,
});

const dataItemToMessage = (item: ChatTypes.Message): AssistantChatMessage => {
  const authorId = item.author?.id;
  const role: AssistantChatMessage['role'] = authorId === assistant.id ? 'assistant' : 'user';

  return {
    role,
    content: item.text,
  };
};

const getMessageHistory = (): AssistantChatMessage[] =>
  [...dataSource.items()].map(dataItemToMessage);

export const useApi = () => {
  const [alerts, setAlerts] = useState<ChatTypes.Alert[]>([]);

  const insertMessage = useCallback((data: ChatTypes.Message): void => {
    dataSource.store().push([{ type: 'insert', data }]);
  }, []);

  const updateLastMessageContent = useCallback((text: string): void => {
    const lastMessage = dataSource.items().at(-1);

    dataSource.store().push([
      {
        type: 'update',
        key: lastMessage.id,
        data: { text },
      },
    ]);
  }, []);

  const alertWithMessage = useCallback((message?: string): void => {
    setAlerts([
      {
        message: message || 'No se pudo obtener respuesta del asistente. Inténtalo nuevamente.',
      },
    ]);

    setTimeout(() => {
      setAlerts([]);
    }, ALERT_TIMEOUT);
  }, []);

  const fetchAIResponse = useCallback(
    async (message: ChatTypes.Message): Promise<void> => {
      const messages = [...getMessageHistory(), dataItemToMessage(message)];

      try {
        const aiResponse = await getAIResponse(messages, 200);

        insertMessage({
          id: Date.now(),
          timestamp: new Date(),
          author: assistant,
          text: aiResponse,
        });
      } catch (error: any) {
        alertWithMessage(error?.response?.data?.message);
      }
    },
    [alertWithMessage, insertMessage],
  );

  const regenerateLastAIResponse = useCallback(async (): Promise<void> => {
    const messageHistory = getMessageHistory();
    updateLastMessageContent(REGENERATION_TEXT);

    try {
      const aiResponse = await getAIResponse(messageHistory.slice(0, -1));

      updateLastMessageContent(aiResponse);
    } catch (error: any) {
      updateLastMessageContent(messageHistory.at(-1)?.content ?? '');
      alertWithMessage(error?.response?.data?.message);
    }
  }, [alertWithMessage, updateLastMessageContent]);

  return {
    alerts,
    insertMessage,
    fetchAIResponse,
    regenerateLastAIResponse,
  };
};
