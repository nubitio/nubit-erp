import React from 'react';
import Button from 'devextreme-react/button';
import './AsyncState.scss';

type AsyncStateVariant = 'empty' | 'error' | 'loading' | 'info';

type AsyncStateProps = {
  variant?: AsyncStateVariant;
  title?: string;
  description?: string;
  compact?: boolean;
  onRetry?: () => void;
  retryText?: string;
};

export const AsyncState = ({
  variant = 'empty',
  title,
  description,
  compact = false,
  onRetry,
  retryText = 'Reintentar',
}: AsyncStateProps) => {
  return (
    <div className={`async-state async-state--${variant} ${compact ? 'is-compact' : ''}`}>
      {title && <div className="async-state__title">{title}</div>}
      {description && <div className="async-state__description">{description}</div>}
      {onRetry && variant === 'error' && (
        <div className="async-state__actions">
          <Button icon="ph ph-arrow-clockwise" text={retryText} stylingMode="outlined" onClick={onRetry} />
        </div>
      )}
    </div>
  );
};

