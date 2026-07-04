import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Button, Card, EmptyState, FormField, TextField, Toggle } from '@nubitio/ui';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import type { TenantDomain } from '../../types';
import { useCpConfirm } from '../../hooks/useCpConfirm';
import { CpAlert, CpForm, CpGrid, CpRowActions } from '../layout';

interface DomainsTabProps {
  tenantId: number;
  domains: TenantDomain[];
  isAdmin: boolean;
  onUpdated: () => void;
}

const S = CP_STRINGS.tenantTabs.domains;

export const DomainsTab = ({ tenantId, domains, isAdmin, onUpdated }: DomainsTabProps) => {
  const { ask, ConfirmHost } = useCpConfirm();
  const [domain, setDomain] = useState('');
  const [isPrimary, setIsPrimary] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const add = useMutation({
    mutationFn: () => controlPlaneClient.addDomain(tenantId, domain, isPrimary),
    onSuccess: () => {
      setDomain('');
      setIsPrimary(false);
      setError(null);
      onUpdated();
    },
    onError: (err: Error) => setError(err.message),
  });

  const setPrimary = useMutation({
    mutationFn: (domainId: number) => controlPlaneClient.setPrimaryDomain(tenantId, domainId),
    onSuccess: () => onUpdated(),
    onError: (err: Error) => setError(err.message),
  });

  const remove = useMutation({
    mutationFn: (domainId: number) => controlPlaneClient.deleteDomain(tenantId, domainId),
    onSuccess: () => onUpdated(),
    onError: (err: Error) => setError(err.message),
  });

  return (
    <CpGrid>
      {error && (
        <CpAlert tone="danger" className="cp-span-full">
          {error}
        </CpAlert>
      )}

      {isAdmin && (
        <Card title={S.addTitle}>
          <CpForm
            onSubmit={(e) => {
              e.preventDefault();
              add.mutate();
            }}
          >
            <FormField label={S.domain}>
              <TextField
                value={domain}
                onChange={(e) => setDomain(e.target.value)}
                placeholder={S.domainPlaceholder}
                required
              />
            </FormField>
            <Toggle
              label={S.setPrimary}
              checked={isPrimary}
              onChange={setIsPrimary}
            />
            <Button type="submit" loading={add.isPending} fullWidth>
              {S.addSubmit}
            </Button>
          </CpForm>
        </Card>
      )}

      <div className={isAdmin ? undefined : 'cp-span-full'}>
        <Card title={S.listTitle}>
          {domains.length === 0 ? (
            <EmptyState icon="globe" title={S.empty} size="sm" />
          ) : (
            <div className="cp-data-table-wrap">
              <table className="cp-data-table">
                <thead>
                  <tr>
                    <th>{S.colDomain}</th>
                    <th>{S.colPrimary}</th>
                    {isAdmin && <th />}
                  </tr>
                </thead>
                <tbody>
                  {domains.map((row) => {
                    const primary = Boolean(row.is_primary);
                    return (
                      <tr key={row.id}>
                        <td>
                          <code className="cp-code">{row.domain}</code>
                        </td>
                        <td>{primary ? CP_STRINGS.yes : CP_STRINGS.no}</td>
                        {isAdmin && (
                          <td className="cp-data-table__actions">
                            <CpRowActions
                              items={[
                                ...(!primary
                                  ? [
                                      {
                                        label: S.setPrimaryAction,
                                        icon: 'star',
                                        onClick: () => setPrimary.mutate(row.id),
                                      },
                                    ]
                                  : []),
                                {
                                  separator: !primary,
                                  label: CP_STRINGS.delete,
                                  icon: 'trash',
                                  danger: true,
                                  onClick: () =>
                                    ask({
                                      title: S.deleteConfirmTitle,
                                      message: S.deleteConfirm(row.domain),
                                      variant: 'danger',
                                      onConfirm: () => remove.mutate(row.id),
                                    }),
                                },
                              ]}
                            />
                          </td>
                        )}
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
      <ConfirmHost />
    </CpGrid>
  );
};