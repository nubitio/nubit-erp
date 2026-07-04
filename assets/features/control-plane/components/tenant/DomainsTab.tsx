import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Button, Card, Code, EmptyState, FormField, TextField, Toggle } from '@nubitio/ui';
import { controlPlaneClient } from '../../api/controlPlaneClient';
import { CP_STRINGS } from '../../i18n/es';
import type { TenantDomain } from '../../types';
import { RowActions, useConfirm } from '@nubitio/ui';
import { CpAlert, CpForm, Col, DataTable, Grid } from '../layout';

interface DomainsTabProps {
  tenantId: number;
  domains: TenantDomain[];
  isAdmin: boolean;
  onUpdated: () => void;
}

const S = CP_STRINGS.tenantTabs.domains;

export const DomainsTab = ({ tenantId, domains, isAdmin, onUpdated }: DomainsTabProps) => {
  const { ask, ConfirmHost } = useConfirm();
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
    <Grid cols={12} gap={4}>
      {error && (
        <Col fullWidth>
          <CpAlert tone="danger">{error}</CpAlert>
        </Col>
      )}

      {isAdmin && (
        <Col spanMd={4}>
          <Card variant="panel" title={S.addTitle}>
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
        </Col>
      )}

      <Col spanMd={isAdmin ? 8 : 12}>
        <Card variant="panel" title={S.listTitle}>
          {domains.length === 0 ? (
            <EmptyState icon="globe" title={S.empty} size="sm" />
          ) : (
            <DataTable
              variant="flush"
              rows={domains}
              getRowKey={(row) => row.id}
              columns={[
                {
                  id: 'domain',
                  header: S.colDomain,
                  render: (row) => <Code>{row.domain}</Code>,
                },
                {
                  id: 'primary',
                  header: S.colPrimary,
                  accessor: (row) => (row.is_primary ? CP_STRINGS.yes : CP_STRINGS.no),
                },
              ]}
              rowActions={
                isAdmin
                  ? (row) => {
                      const primary = Boolean(row.is_primary);
                      return [
                        ...(!primary
                          ? [
                              {
                                label: S.setPrimaryAction,
                                icon: 'star' as const,
                                onClick: () => setPrimary.mutate(row.id),
                              },
                            ]
                          : []),
                        {
                          separator: !primary,
                          label: CP_STRINGS.delete,
                          icon: 'trash' as const,
                          danger: true,
                          onClick: () =>
                            ask({
                              title: S.deleteConfirmTitle,
                              message: S.deleteConfirm(row.domain),
                              variant: 'danger',
                              onConfirm: () => remove.mutate(row.id),
                            }),
                        },
                      ];
                    }
                  : undefined
              }
            />
          )}
        </Card>
      </Col>
      <ConfirmHost />
    </Grid>
  );
};