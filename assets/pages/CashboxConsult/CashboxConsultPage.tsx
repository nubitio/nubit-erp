import './CashboxConsult.scss';
import { DxDataGrid, DxDataGridEvents } from '../../core/datagrid/DxDataGrid';
import { CashboxDetailFields, CashboxFields } from '../Cashbox/CashboxFields';
import { Constants } from '../../core/common/Constants';
import { useRef } from 'react';
import notify from 'devextreme/ui/notify';
import { httpClient } from '../../core/http/HttpClient';
import { userInformation } from '../../core/common/UserInformation';

export const CashboxConsultPage = () => {
  const container = 'wrapper';
  const fields = CashboxFields();
  const detailFields = CashboxDetailFields();
  const gridRef = useRef<DxDataGridEvents>(null);

  const API_URL = Constants.API_BASE_URL + 'cashboxes';

  const toolbar = [
    {
      text: 'Reporte',
      type: 'default',
      onClick: () => {
        let selectedRow = gridRef.current?.getSelectedRow();

        if (!selectedRow) {
          notify(
            {
              message: 'Seleccione un registro',
              type: 'info',
              displayTime: 3000,
            },
            { position: 'top right' },
          );
        }

        httpClient
          .post(
            Constants.API_BASE_URL + `cashboxes/${selectedRow.caja_id}/export`,
            { caja_id: selectedRow.caja_id },
            {
              headers: {
                'Content-Type': 'application/json',
              },
              responseType: 'blob',
            },
          )
          .subscribe((response) => {
            const url = URL.createObjectURL(response.data as Blob);
            window.open(url);
          });
      },
    },
  ];

  return (
    <div id={container}>
      <DxDataGrid
        ref={gridRef}
        id="cashboxes"
        title={'Reportes de caja'}
        toolbar={toolbar}
        url={API_URL}
        detailUrl={API_URL + '/{id}/details'}
        fields={fields}
        detailFields={detailFields}
        filter={[
          {
            field: 'local',
            operator: '=',
            value: userInformation().currentBranchId,
          },
        ]}
      />
    </div>
  );
};
