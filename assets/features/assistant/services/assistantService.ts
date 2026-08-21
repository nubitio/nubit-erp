
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { httpClient } from '@shared/api/HttpClient';
import ApiResponse from '@shared/api/ApiResponse';

export type AssistantChatMessage = {
  role: 'user' | 'assistant';
  content: string;
};

export type AssistantAttachmentPayload = {
  name: string;
  mimeType: string;
  mediaId: string;
  s3Url: string;
};

export type AssistantResponse = {
  message: string;
};

export type AssistantThreadPayload = {
  id: string | null;
  messages: Array<{
    role: 'user' | 'assistant';
    content: string;
    createdAt: string;
    actions: Array<{
      id: string;
      kind: string;
      summary: string;
      payload?: Record<string, unknown>;
      expiresAt?: string;
    }> | null;
  }>;
};

export type AssistantChatRequest = {
  messages: AssistantChatMessage[];
  attachments?: AssistantAttachmentPayload[];
};

class AssistantService {
  chat(
    messages: AssistantChatMessage[],
    attachments?: AssistantAttachmentPayload[],
  ): Promise<AssistantResponse> {
    const body: AssistantChatRequest = { messages };
    if (attachments && attachments.length > 0) {
      body.attachments = attachments;
    }

    return httpClient.post<AssistantResponse>(`${API}assistant`, body).then((response: ApiResponse<AssistantResponse>) => {
      const message = response.data?.message;

      if (!message) {
        throw new Error('Respuesta del asistente inválida');
      }

      return { message };
    });
  }

  currentThread(): Promise<AssistantThreadPayload> {
    return httpClient
      .get<AssistantThreadPayload>(`${API}assistant/thread`)
      .then((response: ApiResponse<AssistantThreadPayload>) => response.data ?? { id: null, messages: [] });
  }

  startNewThread(): Promise<AssistantThreadPayload> {
    return httpClient
      .post<AssistantThreadPayload>(`${API}assistant/thread`, {})
      .then((response: ApiResponse<AssistantThreadPayload>) => response.data ?? { id: null, messages: [] });
  }
}

export const assistantService = new AssistantService();
