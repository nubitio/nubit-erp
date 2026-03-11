import React from 'react';
import { useQuerySuggestions } from '../hooks/useQuerySuggestions';
import './QuerySuggestionChips.scss';

interface QuerySuggestionChipsProps {
  /** Called when a chip is clicked; receives the full query string. */
  onSuggestionClick: (query: string) => void;
  /** When true the chips are hidden (e.g. the user has started typing). */
  hidden?: boolean;
}

export function QuerySuggestionChips({
  onSuggestionClick,
  hidden = false,
}: QuerySuggestionChipsProps) {
  const { suggestions } = useQuerySuggestions();

  if (hidden || suggestions.length === 0) return null;

  return (
    <div className="query-suggestion-chips" aria-label="Sugerencias de consulta">
      {suggestions.map((s) => (
        <button
          key={s.id}
          type="button"
          role="button"
          aria-label={s.query}
          data-testid={`query-chip-${s.id}`}
          className="query-suggestion-chips__chip"
          onClick={() => onSuggestionClick(s.query)}
        >
          {s.label}
        </button>
      ))}
    </div>
  );
}
