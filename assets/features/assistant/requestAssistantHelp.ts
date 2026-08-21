export const ASSISTANT_PREFILL_KEY = 'efact.assistant.prefill';

export function requestAssistantHelp(query: string): void {
  const trimmed = query.trim();
  if (trimmed === '') {
    return;
  }

  sessionStorage.setItem(ASSISTANT_PREFILL_KEY, trimmed);
  window.dispatchEvent(new CustomEvent('efact:assistant-open', { detail: { query: trimmed } }));
  window.dispatchEvent(new CustomEvent('efact:assistant-prefill', { detail: { query: trimmed } }));
}
