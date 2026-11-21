import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  AppDialog,
  Button,
  FormField,
  FormLayout,
  Skeleton,
  TextField,
  Toggle,
} from '@nubitio/ui';
import { tenantOnboardingService } from '@features/onboarding/tenantOnboardingService';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { violationsOf } from '../formErrors';
import { branchService, companyIri, type Branch } from '../organizationService';
import { validateBranch } from '../organizationValidation';
import { onlyDigits } from '../companyValidation';
import { SettingsGroup } from '../settings/SettingsRow';
import { RecordRow } from '../settings/SettingsRecords';

export const BRANCHES_KEY = ['settings', 'branches'] as const;
const FORM_ID = 'branch-form';
const FIELDS = ['name', 'address', 'phone'] as const;

/** "Sucursales": the places the business sells from. They are turned off rather than deleted. */
export const BranchesSection = () => {
  const canEdit = hasPermission('settings.edit');
  const [editing, setEditing] = useState<Branch | 'new' | null>(null);
  const branches = useQuery({ queryKey: BRANCHES_KEY, queryFn: branchService.list });

  if (branches.isError) return <Alert tone="danger">No se pudieron cargar las sucursales.</Alert>;
  if (branches.isPending) return <Skeleton variant="rect" height={160} />;

  return (
    <>
      <SettingsGroup
        title="Sucursales"
        action={
          canEdit && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              icon="ph ph-plus"
              onClick={() => setEditing('new')}
            >
              Nueva sucursal
            </Button>
          )
        }
      >
        {branches.data.length === 0 && <p className="settings-muted">Aún no hay sucursales.</p>}
        {branches.data.map((branch) => (
          <RecordRow
            key={branch.id}
            title={branch.name}
            description={
              [branch.address, branch.phone].filter(Boolean).join(' · ') ||
              'Sin dirección ni teléfono'
            }
            active={branch.isActive}
            editLabel={`Editar sucursal ${branch.name}`}
            onEdit={canEdit ? () => setEditing(branch) : undefined}
          />
        ))}
      </SettingsGroup>
      {editing && (
        <BranchDialog
          branch={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
        />
      )}
    </>
  );
};

const BranchDialog = ({ branch, onClose }: { branch: Branch | null; onClose: () => void }) => {
  const queryClient = useQueryClient();
  const [name, setName] = useState(branch?.name ?? '');
  const [address, setAddress] = useState(branch?.address ?? '');
  const [phone, setPhone] = useState(branch?.phone ?? '');
  const [isActive, setIsActive] = useState(branch?.isActive ?? true);
  const [submitted, setSubmitted] = useState(false);
  const [serverErrors, setServerErrors] = useState<Record<string, string>>({});

  // A new branch belongs to the tenant's one company.
  const snapshot = useQuery({
    queryKey: ['settings', 'company'],
    queryFn: () => tenantOnboardingService.snapshot(),
    enabled: branch === null,
  });
  const companyId = snapshot.data?.company?.id ?? null;

  const issues = validateBranch({ name, address, phone });
  const errorOf = (field: (typeof FIELDS)[number]) =>
    serverErrors[field] || (submitted ? issues[field] : undefined);

  const save = useMutation({
    mutationFn: () => {
      const input = {
        name: name.trim(),
        address: address.trim() || null,
        phone: phone || null,
        isActive,
      };
      return branch
        ? branchService.update(branch.id, input)
        : branchService.create({ ...input, company: companyIri(companyId as string) });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: BRANCHES_KEY });
      toast(branch ? 'Sucursal actualizada' : 'Sucursal creada', 'success');
      onClose();
    },
    onError: (failure) => setServerErrors(violationsOf(failure, FIELDS).fields),
  });

  const submit = () => {
    setSubmitted(true);
    setServerErrors({});
    if (Object.keys(issues).length > 0) return;
    save.mutate();
  };

  const saving = save.isPending;
  const missingCompany = branch === null && !snapshot.isPending && companyId === null;

  return (
    <AppDialog
      open
      title={branch ? 'Editar sucursal' : 'Nueva sucursal'}
      width={480}
      closeOnOutsideClick={false}
      closeOnEscape={!saving}
      showCloseButton={!saving}
      onClose={() => {
        if (!saving) onClose();
      }}
      footer={
        <>
          <Button type="button" variant="secondary" disabled={saving} onClick={onClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form={FORM_ID}
            variant="primary"
            loading={saving}
            disabled={branch === null && companyId === null}
          >
            {branch ? 'Guardar cambios' : 'Crear sucursal'}
          </Button>
        </>
      }
    >
      <FormLayout
        id={FORM_ID}
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          submit();
        }}
      >
        {missingCompany && <Alert tone="warning">Registra primero los datos de la empresa.</Alert>}
        <FormField label="Nombre" htmlFor="branch-name" required error={errorOf('name')}>
          <TextField
            id="branch-name"
            autoFocus
            required
            maxLength={255}
            value={name}
            invalid={Boolean(errorOf('name'))}
            onChange={(event) => setName(event.target.value)}
          />
        </FormField>
        <FormField label="Dirección" htmlFor="branch-address" error={errorOf('address')}>
          <TextField
            id="branch-address"
            maxLength={255}
            value={address}
            invalid={Boolean(errorOf('address'))}
            onChange={(event) => setAddress(event.target.value)}
          />
        </FormField>
        <FormField
          label="Teléfono"
          htmlFor="branch-phone"
          helpText="Solo números, de 6 a 10 dígitos."
          error={errorOf('phone')}
        >
          <TextField
            id="branch-phone"
            type="tel"
            inputMode="numeric"
            maxLength={10}
            value={phone}
            invalid={Boolean(errorOf('phone'))}
            onChange={(event) => setPhone(onlyDigits(event.target.value, 10))}
          />
        </FormField>
        <Toggle checked={isActive} onChange={setIsActive} label="Sucursal activa" />
      </FormLayout>
    </AppDialog>
  );
};
