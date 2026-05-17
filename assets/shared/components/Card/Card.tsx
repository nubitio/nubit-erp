import React from 'react';

import type { SingleCardProps } from '@app/types';

import './Card.scss';

export const Card = ({
  title,
  description,
  children,
}: React.PropsWithChildren<SingleCardProps>) => {
  return (
    <div className="app-card">
      <div className="app-card__content content">
        <div className="header">
          <div className="title">{title}</div>
          <div className="description">{description}</div>
        </div>
        {children}
      </div>
    </div>
  );
};
