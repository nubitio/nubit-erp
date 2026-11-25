import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  AppDialog,
  Button,
  Combobox,
  FormField,
  FormLayout,
  Skeleton,
  TextField,
  Toggle,
} from '@nubitio/ui';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { violationsOf } from '../formErrors';
import {
  branchIri,
  branchService,
  seriesService,
  voucherTypeIri,
  voucherTypeService,
  type VoucherSeries,
} from '../organizationService';
import { normalizeSeries, validateSeries } from '../organizationValidation';
import { SettingsGroup } from '../settings/SettingsRow';
import { RecordRow } from '../settings/SettingsRecords';
import { BRANCHES_KEY } from './BranchesSection';

const SERIES_KEY = ['settings', 'voucher-series'] as const;
const TYPES_KEY = ['settings', 'voucher-types'] as const;
const FORM_ID = 'series-form';
const FIELDS = ['branch', 'voucherType', 'series', 'associatedSeries'] as const;

/** "Series": the prefix of each voucher number, per branch and type of document. */
export const SeriesSection = () => {
  const canEdit = hasPermission('settings.edit');
  const [editing, setEditing] = useState<VoucherSeries | 'new' | null>(null);
  const series = useQuery({ queryKey: SERIES_KEY, queryFn: seriesService.list });

  if (series.isLoadingError) return <Alert tone="danger">No se pudieron cargar las series.</Alert>;
  if (series.isPending) return <Skeleton variant="rect" height={160} />;

  const sorted = [...series.data].sort(
    (a, b) =>
      a.branch.name.localeCompare(b.branch.name) ||
      a.voucherType.code.localeCompare(b.voucherType.code) ||
      a.series.localeCompare(b.series),
  );
  return (
    <>
      <SettingsGroup
        title="Series de comprobante"
        action={
          canEdit && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              icon="ph ph-plus"
              data-testid="new-voucher-series"
              onClick={() => setEditing('new')}
            >
              Nueva serie
            </Button>
          )
        }
      >
        {sorted.length === 0 && (
          <p className="settings-muted">
            Aún no hay series. Configura una por tipo de documento para facturar.
          </p>
        )}
        {sorted.map((item) => (
          <RecordRow
            key={item.id}
            title={`${item.series} · ${item.voucherType.name}`}
            description={
              item.associatedSeries
                ? `${item.branch.name} · Asociada a ${item.associatedSeries}`
                : item.branch.name
            }
            active={item.isActive}
            editLabel={`Editar serie ${item.series}`}
            onEdit={canEdit ? () => setEditing(item) : undefined}
          />
        ))}
      </SettingsGroup>
      {editing && (
        <SeriesDialog
          item={editing === 'new' ? null : editing}
          all={series.data}
          onClose={() => setEditing(null)}
        />
      )}
    </>
  );
};

const SeriesDialog = ({
  item,
  all,
  onClose,
}: {
  item: VoucherSeries | null;
  all: VoucherSeries[];
  onClose: () => void;
}) => {
  const queryClient = useQueryClient();
  const branches = useQuery({ queryKey: BRANCHES_KEY, queryFn: branchService.list });
  const types = useQuery({ queryKey: TYPES_KEY, queryFn: voucherTypeService.list });

  const [branchId, setBranchId] = useState<string | null>(item?.branch.id ?? null);
  const [typeCode, setTypeCode] = useState<string | null>(item?.voucherType.code ?? null);
  const [series, setSeries] = useState(item?.series ?? '');
  const [associated, setAssociated] = useState(item?.associatedSeries ?? '');
  const [isActive, setIsActive] = useState(item?.isActive ?? true);
  const [submitted, setSubmitted] = useState(false);
  const [serverErrors, setServerErrors] = useState<ReturnType<typeof violationsOf> | null>(null);

  const issues = validateSeries(
    { branchId, typeCode, series, associatedSeries: associated },
    all,
    item,
  );
  const errorOf = (field: keyof typeof issues, server?: string) =>
    (server ? serverErrors?.fields[server] : undefined) || (submitted ? issues[field] : undefined);

  const save = useMutation({
    mutationFn: () => {
      const input = {
        branch: branchIri(branchId as string),
        voucherType: voucherTypeIri(typeCode as string),
        series,
        associatedSeries: associated || null,
        isActive,
      };
      return item ? seriesService.update(item.id, input) : seriesService.create(input);
    },
    onSuccess: ({ data: saved }) => {
      queryClient.setQueryData<VoucherSeries[]>(SERIES_KEY, (current = []) =>
        current.some((other) => other.id === saved.id)
          ? current.map((other) => (other.id === saved.id ? saved : other))
          : [...current, saved],
      );
      void queryClient.invalidateQueries({ queryKey: SERIES_KEY });
      toast(item ? 'Serie actualizada' : 'Serie creada', 'success');
      onClose();
    },
    onError: (failure) => setServerErrors(violationsOf(failure, FIELDS)),
  });

  const submit = () => {
    setSubmitted(true);
    setServerErrors(null);
    if (Object.keys(issues).length > 0) return;
    save.mutate();
  };

  const saving = save.isPending;
  const branchOptions = (branches.data ?? [])
    .filter((branch) => branch.isActive || branch.id === item?.branch.id)
    .map((branch) => ({ value: branch.id, label: branch.name }));
  const typeOptions = (types.data ?? [])
    .filter((type) => type.isActive || type.code === item?.voucherType.code)
    .map((type) => ({ value: type.code, label: `${type.name} (${type.code})` }));

  return (
    <AppDialog
      open
      title={item ? 'Editar serie' : 'Nueva serie'}
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
            data-testid="save-voucher-series"
          >
            {item ? 'Guardar cambios' : 'Crear serie'}
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
        {serverErrors?.general && <Alert tone="danger">{serverErrors.general}</Alert>}
        {(branches.isError || types.isError) && (
          <Alert tone="danger">
            No se pudieron cargar las sucursales o los tipos de comprobante.
          </Alert>
        )}
        {item && (
          <Alert tone="info">
            Cambiar la serie no renumera los comprobantes ya emitidos. Si dejas de usarla,
            desactívala y crea otra.
          </Alert>
        )}
        <FormField
          label="Sucursal"
          htmlFor="series-branch"
          required
          helpText="Puedes elegir cualquier sucursal activa de la empresa, aunque hayas iniciado sesión en otra."
          error={errorOf('branchId', 'branch')}
        >
          <Combobox
            id="series-branch"
            aria-label="Sucursal"
            options={branchOptions}
            value={branchId}
            disabled={saving || branches.isPending}
            invalid={Boolean(errorOf('branchId', 'branch'))}
            onChange={(value) => {
              setServerErrors(null);
              setBranchId(value);
            }}
          />
        </FormField>
        <FormField
          label="Tipo de comprobante"
          htmlFor="series-type"
          required
          error={errorOf('typeCode', 'voucherType')}
        >
          <Combobox
            id="series-type"
            aria-label="Tipo de comprobante"
            options={typeOptions}
            value={typeCode}
            disabled={saving || types.isPending}
            invalid={Boolean(errorOf('typeCode', 'voucherType'))}
            onChange={(value) => {
              setServerErrors(null);
              setTypeCode(value);
            }}
          />
        </FormField>
        <FormField
          label="Serie"
          htmlFor="series-code"
          required
          helpText="4 letras o números, por ejemplo F001 o B001."
          error={errorOf('series', 'series')}
        >
          <TextField
            id="series-code"
            required
            disabled={saving}
            maxLength={4}
            autoCapitalize="characters"
            value={series}
            invalid={Boolean(errorOf('series', 'series'))}
            onChange={(event) => {
              setServerErrors(null);
              setSeries(normalizeSeries(event.target.value));
            }}
          />
        </FormField>
        <FormField
          label="Serie asociada"
          htmlFor="series-associated"
          helpText="Solo para notas de crédito o débito: la serie del comprobante que modifican."
          error={errorOf('associatedSeries', 'associatedSeries')}
        >
          <TextField
            id="series-associated"
            disabled={saving}
            maxLength={4}
            autoCapitalize="characters"
            value={associated}
            invalid={Boolean(errorOf('associatedSeries', 'associatedSeries'))}
            onChange={(event) => {
              setServerErrors(null);
              setAssociated(normalizeSeries(event.target.value));
            }}
          />
        </FormField>
        <Toggle checked={isActive} onChange={setIsActive} label="Serie activa" disabled={saving} />
      </FormLayout>
    </AppDialog>
  );
};
