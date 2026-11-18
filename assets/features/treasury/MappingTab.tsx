import { Alert, Button, EmptyState, SelectField, Skeleton, Text } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { hasPermission } from '@shared/permissions';
import {
  treasuryErrorMessage,
  usePaymentMethodMapping,
  useSetMapping,
  useTreasuryPosition,
} from './api/treasuryApi';
import {
  mappableAccounts,
  mappableCurrencies,
  mappableMethods,
  mappedAccountId,
} from './model/treasuryModel';

export const MappingTab = () => {
  const position = useTreasuryPosition();
  const mapping = usePaymentMethodMapping();
  const setMapping = useSetMapping();
  const canManage = hasPermission('treasury.manage');

  if (position.isPending || mapping.isPending) return <Skeleton height={220} />;
  if (position.isError || mapping.isError) {
    return (
      <Alert tone="danger">
        {treasuryErrorMessage(position.error ?? mapping.error, 'No se pudo cargar el mapeo.')}{' '}
        <Button
          variant="secondary"
          size="sm"
          onClick={() => {
            void position.refetch();
            void mapping.refetch();
          }}
        >
          Reintentar
        </Button>
      </Alert>
    );
  }

  const { accounts } = position.data;
  const currencies = mappableCurrencies(accounts);
  const methods = mappableMethods(mapping.data.paymentMethods);

  if (currencies.length === 0) {
    return (
      <EmptyState
        size="sm"
        icon="bank"
        title="Primero crea una cuenta"
        description="Para asignar métodos de pago necesitas al menos un banco o una billetera. Sincroniza o crea una en la pestaña Cuentas."
      />
    );
  }

  const change = (branchId: string, paymentMethodId: number, currency: string, accountId: string) =>
    setMapping.mutate(
      { branchId, paymentMethodId, currency, accountId: accountId === '' ? null : accountId },
      {
        onSuccess: () => toast('Asignación guardada', 'success'),
        onError: (error) =>
          toast(treasuryErrorMessage(error, 'No se pudo guardar la asignación'), 'error', 6000),
      },
    );

  return (
    <div className="treasury__stack">
      <Text tone="muted" as="p">
        Elige en qué cuenta entra cada método de pago. El efectivo siempre entra en la caja abierta
        del usuario. Un método sin cuenta deja el dinero en «Sin asignar» hasta que lo
        reclasifiques.
      </Text>

      {mapping.data.branches.map((branch) => (
        <section
          key={branch.id}
          className="treasury__branch"
          aria-label={`Sucursal ${branch.name}`}
        >
          {mapping.data.branches.length > 1 && (
            <h3 className="treasury__branch-title">{branch.name}</h3>
          )}
          <table className="nb-data-table treasury__mapping">
            <thead>
              <tr>
                <th scope="col">Método de pago</th>
                {currencies.map((currency) => (
                  <th key={currency} scope="col">
                    Cuenta en {currency}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {methods.map((method) => (
                <tr key={method.id}>
                  <th scope="row">{method.name}</th>
                  {currencies.map((currency) => (
                    <td key={currency}>
                      <SelectField
                        aria-label={`${method.name} en ${currency}, ${branch.name}`}
                        value={
                          mappedAccountId(mapping.data.mappings, branch.id, method.id, currency) ??
                          ''
                        }
                        disabled={!canManage || setMapping.isPending}
                        onChange={(event) =>
                          change(branch.id, method.id, currency, event.target.value)
                        }
                      >
                        <option value="">Sin asignar</option>
                        {mappableAccounts(accounts, currency).map((account) => (
                          <option key={account.id} value={account.id}>
                            {account.name}
                          </option>
                        ))}
                      </SelectField>
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      ))}
    </div>
  );
};
