import React from 'react';

import ScrollView from 'devextreme-react/scroll-view';
import { Card } from '@shared/ui';

import type { SingleCardProps } from '@app/types';

import './single-card.scss';

export const SingleCard = ({
  title,
  description,
  children,
}: React.PropsWithChildren<SingleCardProps>) => {
  return (
    <ScrollView height="100%" width="100%" className="view-wrapper-scroll single-card">
      <Card title={title} description={description}>
        {children}
      </Card>
    </ScrollView>
  );
};
