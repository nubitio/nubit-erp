import { useMemo } from 'react';
import { getCoreLocale, getCoreTimezone } from '@nubitio/core';
import { Timeline, TimelineItem } from '@nubitio/ui';
import {
  buildSunatLifecycleSteps,
  type DocumentLifecycleInput,
} from '../lib/buildSunatLifecycleSteps';
import './DocumentStatusTimeline.scss';

export interface DocumentStatusTimelineProps {
  document: DocumentLifecycleInput;
  title?: string;
  description?: string;
  className?: string;
}

function formatLifecycleTimestamp(iso: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  return date.toLocaleString(getCoreLocale(), { timeZone: getCoreTimezone() });
}

export function DocumentStatusTimeline({
  document,
  title = 'Estado SUNAT',
  description = 'Ciclo de vida del comprobante.',
  className,
}: DocumentStatusTimelineProps) {
  const steps = useMemo(
    () => buildSunatLifecycleSteps(document, formatLifecycleTimestamp),
    [document],
  );

  return (
    <Timeline
      variant="stepper"
      title={title}
      description={description}
      className={className}
      aria-label={title}
    >
      {steps.map((step) => (
        <TimelineItem
          key={step.key}
          status={step.status}
          title={step.title}
          timestamp={step.timestamp}
          dateTime={step.dateTime}
        />
      ))}
    </Timeline>
  );
}