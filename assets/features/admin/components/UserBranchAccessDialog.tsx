import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import {
  Alert,
  AppDialog,
  Button,
  Checkbox,
  Combobox,
  FormField,
  FormLayout,
  Stack,
} from '@nubitio/ui';

import { httpClient } from '@shared/api/HttpClient';
import toast from '@shared/lib/SharedUtils';

const API = getCoreApiBaseUrl();
const FORM_ID = 'user-branch-access-form';

interface Assignment {
  operatingBranchIds: string[] | null;
  defaultBranchId: string | null;
}

interface Branch {
  id: string;
  name: string;
  isActive: boolean;
}

export const UserBranchAccessDialog = ({
  userId,
  username,
  onClose,
}: {
  userId: string | number;
  username: string;
  onClose: () => void;
}) => {
  const endpoint = `${API}users/${userId}/branch-access`;
  const options = useQuery({
    queryKey: ['admin', 'branch-access', userId],
    queryFn: async () => {
      const [assignment, branches] = await Promise.all([
        httpClient.get<Assignment>(endpoint),
        httpClient.get<Branch[] | { member?: Branch[]; 'hydra:member'?: Branch[] }>(
          `${API}branches?pagination=false`,
        ),
      ]);
      const data = branches.data;
      return {
        assignment: assignment.data,
        branches: Array.isArray(data) ? data : (data.member ?? data['hydra:member'] ?? []),
      };
    },
    staleTime: 0,
  });
  const [draft, setDraft] = useState<Assignment | null>(null);
  const assigned = draft ?? options.data?.assignment;
  const branches = options.data?.branches ?? [];
  const selected = assigned?.operatingBranchIds ?? branches.map((branch) => branch.id);
  const save = useMutation({
    mutationFn: () =>
      httpClient.put<Assignment>(
        endpoint,
        { operatingBranchIds: selected, defaultBranchId: assigned?.defaultBranchId ?? null },
        { errorPresentation: 'inline' },
      ),
    onSuccess: () => {
      toast('Acceso a sucursales actualizado.', 'success');
      onClose();
    },
  });

  return (
    <AppDialog
      open
      title={`Sucursales de ${username}`}
      onClose={() => {
        if (!save.isPending) onClose();
      }}
      closeOnEscape={!save.isPending}
      showCloseButton={!save.isPending}
      footer={
        <>
          <Button variant="secondary" disabled={save.isPending} onClick={onClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form={FORM_ID}
            variant="primary"
            data-testid="save-user-branch-access"
            loading={save.isPending}
            disabled={options.isFetching || !assigned || options.isError}
          >
            Guardar acceso
          </Button>
        </>
      }
    >
      <FormLayout
        id={FORM_ID}
        onSubmit={(event) => {
          event.preventDefault();
          if (assigned && !options.isFetching && !options.isError && !save.isPending) save.mutate();
        }}
      >
        {options.isPending && <p>Cargando sucursales…</p>}
        {options.isError && <Alert tone="danger">No se pudo cargar el acceso a sucursales.</Alert>}
        {assigned && !options.isError && (
          <>
            {assigned.operatingBranchIds === null && (
              <Alert tone="info">
                Esta cuenta conserva acceso a todos los locales. Al guardar, el acceso quedará
                limitado a los que selecciones.
              </Alert>
            )}
            <Stack>
              {branches.map((branch) => (
                <Checkbox
                  key={branch.id}
                  label={branch.isActive ? branch.name : `${branch.name} (inactiva)`}
                  checked={selected.includes(branch.id)}
                  disabled={save.isPending || options.isFetching}
                  onChange={(event) => {
                    const ids = event.target.checked
                      ? [...selected, branch.id]
                      : selected.filter((id) => id !== branch.id);
                    setDraft({
                      operatingBranchIds: ids,
                      defaultBranchId:
                        assigned.defaultBranchId && ids.includes(assigned.defaultBranchId)
                          ? assigned.defaultBranchId
                          : null,
                    });
                  }}
                />
              ))}
            </Stack>
            <FormField label="Sucursal predeterminada">
              <Combobox
                aria-label="Sucursal predeterminada"
                options={branches
                  .filter((branch) => branch.isActive && selected.includes(branch.id))
                  .map((branch) => ({ value: branch.id, label: branch.name }))}
                value={assigned.defaultBranchId}
                disabled={save.isPending || options.isFetching}
                onChange={(id) => setDraft({ operatingBranchIds: selected, defaultBranchId: id })}
              />
            </FormField>
          </>
        )}
        {save.isError && (
          <Alert tone="danger">
            {(save.error as { data?: { message?: string } })?.data?.message ??
              'No se pudo guardar el acceso a sucursales.'}
          </Alert>
        )}
      </FormLayout>
    </AppDialog>
  );
};
