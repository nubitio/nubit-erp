import type { ElementType } from 'react';

export type AppRouteDefinition = {
  path: string;
  element: ElementType;
  children?: AppRouteDefinition[];
};
