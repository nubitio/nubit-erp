import React from 'react';

import { Card } from '@shared/ui';
import type { CardProps } from '@nubitio/ui';

import './single-card.scss';

export const SingleCard = ({
  title,
  description,
  children,
}: React.PropsWithChildren<CardProps>) => {
  return (
    <div className="view-wrapper-scroll single-card">
      <Card title={title} description={description}>
        {children}
      </Card>
    </div>
  );
};
