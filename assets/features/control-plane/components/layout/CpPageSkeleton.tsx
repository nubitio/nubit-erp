import { Grid, Page, Skeleton } from '@nubitio/ui';
import { CpMetricGrid } from './CpMetricGrid';

type CpPageSkeletonVariant = 'dashboard' | 'table' | 'detail' | 'form' | 'matrix';

interface CpPageSkeletonProps {
  variant?: CpPageSkeletonVariant;
}

export const CpPageSkeleton = ({ variant = 'table' }: CpPageSkeletonProps) => (
  <Page aria-busy="true" aria-live="polite">
    <div className="cp-page-skeleton__header">
      <Skeleton variant="text" width="12rem" height={28} />
      <Skeleton variant="text" width="20rem" height={16} />
    </div>

    {variant === 'dashboard' && (
      <>
        <CpMetricGrid items={[]} loading />
        <Grid cols={2}>
          <CpMetricGrid items={[]} loading layout="strip" density="compact" />
          <CpMetricGrid items={[]} loading layout="strip" density="compact" />
        </Grid>
        <Skeleton variant="rect" height={180} />
      </>
    )}

    {variant === 'table' && (
      <>
        <Skeleton variant="rect" height={72} />
        <Skeleton variant="rect" height={320} />
      </>
    )}

    {variant === 'detail' && (
      <>
        <div className="cp-page-skeleton__tabs">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} variant="rect" width={96} height={32} />
          ))}
        </div>
        <Grid cols={2}>
          <Skeleton variant="rect" height={220} />
          <Skeleton variant="rect" height={220} />
        </Grid>
      </>
    )}

    {variant === 'form' && <Skeleton variant="rect" height={360} />}

    {variant === 'matrix' && <Skeleton variant="rect" height={420} />}
  </Page>
);
