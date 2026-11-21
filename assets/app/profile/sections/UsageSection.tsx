import { Alert, Progress, Skeleton } from '@nubitio/ui';
import { useEntitlements } from '@shared/hooks/useEntitlements';
import { SettingsGroup, SettingsRow } from '../settings/SettingsRow';
import { planLabel, usageRows } from '../usageFormat';

/** What the tenant has used of what its plan allows. */
export const UsageSection = () => {
  const { data, isPending, isError } = useEntitlements();

  if (isError) return <Alert tone="warning">No se pudo cargar el uso de tu plan.</Alert>;
  if (isPending) return <Skeleton variant="rect" height={200} />;

  return (
    <SettingsGroup title={`Plan ${planLabel(data.plan)}`}>
      {usageRows(data.limits).map((row) => (
        <SettingsRow
          key={row.key}
          label={row.label}
          description={row.near ? 'Cerca del límite de tu plan.' : undefined}
        >
          <div className="settings-usage">
            {row.ratio !== null && (
              <Progress aria-label={row.label} value={row.ratio * 100} max={100} />
            )}
            <span className="settings-muted">{row.text}</span>
          </div>
        </SettingsRow>
      ))}
    </SettingsGroup>
  );
};
