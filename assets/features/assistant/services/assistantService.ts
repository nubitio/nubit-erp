import { lastValueFrom } from 'rxjs';
import { Constants } from '../../../core/common/Constants';
import { httpClient } from '../../../shared/api/HttpClient';
import ApiResponse from '../../../shared/api/ApiResponse';

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

    return lastValueFrom(
      httpClient.post<AssistantResponse>(`${Constants.API_BASE_URL}assistant`, body),
    ).then((response: ApiResponse<AssistantResponse>) => {
      const message = response.data?.message;

      if (!message) {
        throw new Error('Respuesta del asistente inválida');
      }

      return { message };
    });
  }
}

export const assistantService = new AssistantService();
