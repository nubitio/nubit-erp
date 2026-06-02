import React, { ReactNode } from 'react';
import { StatCard } from '@nubit/ui';
import './CardAnalytics.scss';

type CardProps = {
  title?: string;
  additionalHeaderContent?: ReactNode;
  contentClass: string;
  isLoading?: boolean;
  menuVisible?: boolean;
};

const menuItems = [{ label: 'Configure' }, { label: 'Remove' }];

export const CardAnalytics = ({
  title,
  contentClass,
  isLoading = false,
  children,
  additionalHeaderContent,
  menuVisible = true,
}: React.PropsWithChildren<CardProps>) => (
  <StatCard
    title={title}
    className={contentClass}
    headerExtra={additionalHeaderContent}
    menuVisible={menuVisible}
    menuItems={menuItems}
    isLoading={isLoading}
  >
    {children}
  </StatCard>
);
