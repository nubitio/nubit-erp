import React from 'react';
import './TickerCard.scss';

export const TickerCard = ({
  title,
  icon,
  tone,
  value,
  percentage,
  formatValue = (value) => `${value}`,
}: {
  title: string;
  icon: string;
  tone?: 'warning' | 'info' | 'positive' | 'negative' | 'neutral';
  value: number;
  percentage?: number;
  formatValue?: (value: number) => string;
}) => (
  <div className="ticker">
    <div
      className={`icon-wrapper ${tone || (percentage && percentage > 0 ? 'positive' : 'negative')}`}
    >
      <i className={`dx-icon ${icon}`} />
    </div>
    <div className="middle">
      <div className="title">{title}</div>
      <div className="total">{formatValue(value)}</div>
    </div>
    {percentage !== undefined && (
      <div className={`percentage ${tone || (percentage > 0 ? 'positive' : 'negative')}`}>
        <div className={`dx-icon-${percentage > 0 ? 'spinup' : 'spindown'}`} />
        <div className="value">{`${Math.abs(percentage)}%`}</div>
      </div>
    )}
  </div>
);
