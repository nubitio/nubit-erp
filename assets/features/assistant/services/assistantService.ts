import { lastValueFrom } from 'rxjs';
import { Constants } from '../../../core/common/Constants';
import { httpClient } from '../../../shared/api/HttpClient';
import ApiResponse from '../../../shared/api/ApiResponse';

export type AssistantChatMessage = {
  role: 'user' | 'assistant';
  content: string;
};

export type AssistantResponse = {
  message: string;
};

class AssistantService {
  chat(messages: AssistantChatMessage[]): Promise<AssistantResponse> {
    return lastValueFrom(
      httpClient.post<AssistantResponse>(`${Constants.API_BASE_URL}assistant`, { messages }),
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
