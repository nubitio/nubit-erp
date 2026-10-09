import { describe, expect, it } from 'vitest';
import { buildSunatLifecycleSteps } from './buildSunatLifecycleSteps';

const format = (iso: string) => iso;

describe('buildSunatLifecycleSteps', () => {
  it('marks all steps pending when the document has not been sent', () => {
    const steps = buildSunatLifecycleSteps({ createdAt: '2026-04-20T09:14:00Z' }, format);
    expect(steps.map((step) => step.status)).toEqual(['complete', 'pending', 'pending', 'pending']);
  });

  it('marks awaiting CDR as current when status is pendiente', () => {
    const steps = buildSunatLifecycleSteps({ sunatStatus: 'pendiente' }, format);
    expect(steps.find((step) => step.key === 'awaiting-cdr')?.status).toBe('current');
    expect(steps.find((step) => step.key === 'accepted')?.status).toBe('pending');
  });

  it('marks accepted when SUNAT returns code 0', () => {
    const steps = buildSunatLifecycleSteps({ sunatStatus: '0' }, format);
    expect(steps.find((step) => step.key === 'accepted')?.status).toBe('complete');
    expect(steps.find((step) => step.key === 'accepted')?.title).toBe('Aceptado por SUNAT');
  });

  it('marks rejected for SUNAT error codes', () => {
    const steps = buildSunatLifecycleSteps({ sunatStatus: '2300' }, format);
    expect(steps.find((step) => step.key === 'accepted')?.status).toBe('error');
    expect(steps.find((step) => step.key === 'accepted')?.title).toBe('Rechazado · código 2300');
  });
});
