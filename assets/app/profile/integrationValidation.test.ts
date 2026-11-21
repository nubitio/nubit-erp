import { describe, expect, it } from 'vitest';
import { validateApiClientName, validateWebhookUrl } from './integrationValidation';

describe('validateWebhookUrl', () => {
  it('takes an https address', () => {
    expect(validateWebhookUrl('https://panel.nubit.pe/api/fiscal/efact/webhook')).toBeNull();
    expect(validateWebhookUrl('  https://example.com  ')).toBeNull();
  });

  it('refuses what is empty, malformed or not https', () => {
    expect(validateWebhookUrl('')).not.toBeNull();
    expect(validateWebhookUrl('panel.nubit.pe/webhook')).not.toBeNull();
    expect(validateWebhookUrl('http://example.com')).not.toBeNull();
    expect(validateWebhookUrl('ftp://example.com')).not.toBeNull();
  });

  it('refuses credentials embedded in the address', () => {
    expect(validateWebhookUrl('https://user:pass@example.com/hook')).not.toBeNull();
  });
});

describe('validateApiClientName', () => {
  it('needs a name of a reasonable length', () => {
    expect(validateApiClientName('nubit-control')).toBeNull();
    expect(validateApiClientName('  ')).not.toBeNull();
    expect(validateApiClientName('a'.repeat(121))).not.toBeNull();
  });
});
