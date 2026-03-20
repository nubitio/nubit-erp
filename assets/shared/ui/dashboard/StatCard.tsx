import { percentFormatter } from './formatters';
import './StatCard.scss';

type StatCardProps = {
  title: string;
  value: string;
  description?: string;
  accent?: 'blue' | 'green' | 'yellow' | 'rose' | 'purple' | 'gray';
  delta?: number | null;
  deltaLabel?: string;
};

export const StatCard = ({
  title,
  value,
  description,
  accent = 'blue',
  delta,
  deltaLabel = 'vs periodo anterior',
}: StatCardProps) => (
  <div className={`metric-card accent-${accent}`}>
    <div className="metric-card__title">{title}</div>
    <div className="metric-card__value">{value}</div>
    <div className="metric-card__footer">
      <span>{description}</span>
      {delta !== undefined && delta !== null && (
        <span className={`metric-card__delta ${delta >= 0 ? 'up' : 'down'}`}>
          {delta >= 0 ? '▲' : '▼'} {percentFormatter(Math.abs(delta))} {deltaLabel}
        </span>
      )}
    </div>
  </div>
);
