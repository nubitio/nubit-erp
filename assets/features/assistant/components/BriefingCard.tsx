import React, { useState } from 'react';
import { marked } from 'marked';
import HTMLReactParser from 'html-react-parser';
import { useQuery } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import ApiResponse from '@shared/api/ApiResponse';
import './BriefingCard.scss';

const API = getCoreApiBaseUrl();

type BriefingResponse = {
  content: string;
};

async function fetchBriefing(): Promise<BriefingResponse> {
  const response = await httpClient.get<BriefingResponse>(`${API}assistant/briefing`);
  return (response as ApiResponse<BriefingResponse>).data ?? { content: '' };
}

type BriefingCardProps = {
  /** Show a compact one-line teaser with expand toggle (default: false = full card). */
  compact?: boolean;
};

/**
 * Renders the AI-generated daily briefing on the dashboard.
 *
 * The briefing is pre-generated at 07:30 America/Lima by the Symfony Scheduler
 * and cached in Redis for 20h. This component fetches it from GET /api/assistant/briefing,
 * which serves the cached value instantly.
 */
export function BriefingCard({ compact = false }: BriefingCardProps) {
  const [expanded, setExpanded] = useState(!compact);

  const { data, isLoading, isError } = useQuery({
    queryKey: ['assistant', 'briefing', 'today'],
    queryFn: fetchBriefing,
    // 1 hour stale time — briefing changes only once per day
    staleTime: 60 * 60 * 1000,
    // Don't retry on 401/403 (unauthenticated page)
    retry: (failCount, error) => {
      if (error instanceof Error && error.message.includes('40')) return false;
      return failCount < 2;
    },
  });

  // Don't render anything while loading or on error to avoid layout shift
  if (isLoading || isError || !data?.content) return null;

  const htmlContent = marked(data.content) as string;

  if (compact) {
    return (
      <div className="briefing-card briefing-card--compact">
        <button
          type="button"
          className="briefing-card__toggle"
          onClick={() => setExpanded((v) => !v)}
          aria-expanded={expanded}
        >
          <i className="ph ph-sparkle" aria-hidden="true" />
          <span>Resumen del día</span>
          <i className={`ph ${expanded ? 'ph-caret-up' : 'ph-caret-down'}`} aria-hidden="true" />
        </button>
        {expanded && <div className="briefing-card__body">{HTMLReactParser(htmlContent)}</div>}
      </div>
    );
  }

  return (
    <div className="briefing-card">
      <div className="briefing-card__header">
        <i className="ph ph-sparkle" aria-hidden="true" />
        <span className="briefing-card__title">Resumen del día</span>
      </div>
      <div className="briefing-card__body">{HTMLReactParser(htmlContent)}</div>
    </div>
  );
}
