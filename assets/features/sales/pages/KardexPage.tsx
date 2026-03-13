import './KardexPage.scss';
import React, { useCallback, useRef, useState } from 'react';

import { Item as ToolbarItem, Toolbar } from 'devextreme-react/toolbar';
import Button from 'devextreme-react/button';
import ScrollView from 'devextreme-react/scroll-view';
import DateBox from 'devextreme-react/date-box';
import LoadIndicator from 'devextreme-react/load-indicator';
import DataGrid, {
  Column,
  DataGridRef,
  FilterRow,
  MasterDetail,
  Selection,
} from 'devextreme-react/data-grid';
import { DateUtils } from '@shared/lib/DateUtils';
import { KardexDetailPage } from './KardexDetailPage';
import { useKardexQuery } from './useKardexQuery';

export const KardexPage = () => {
  const gridRef = useRef<DataGridRef>(null);
  const [dateFrom, setDateFrom] = useState<Date>(new Date());
  const [dateTo, setDateTo] = useState<Date>(new Date());

  const { rows, isLoading, isError, errorMessage, refetch } = useKardexQuery(dateFrom, dateTo);

  const renderDetail = useCallback(
    ({ data }: { data: { producto_id: string; [key: string]: unknown } }) => {
      return <KardexDetailPage data={data} dateFrom={dateFrom} dateTo={dateTo} />;
    },
    [dateFrom, dateTo],
  );

  return (
    <ScrollView className="view-wrapper-scroll">
      <div className="view-wrapper view-wrapper-details">
        <Toolbar className="toolbar-details">
          <ToolbarItem location="before">
            <Button icon="arrowleft" onClick={() => window.history.back()} />
          </ToolbarItem>
          <ToolbarItem location="before" text="Kardex" />
          <ToolbarItem location="after" locateInMenu="auto">
            <DateBox
              displayFormat={'dd/MM/yyyy'}
              type={'date'}
              label={'Desde'}
              value={dateFrom}
              onValueChanged={(e) => {
                setDateFrom(e.value);
              }}
              stylingMode={'outlined'}
            />
          </ToolbarItem>
          <ToolbarItem location="after" locateInMenu="auto">
            <DateBox
              displayFormat={'dd/MM/yyyy'}
              type={'date'}
              label={'Hasta'}
              value={dateTo}
              onValueChanged={(e) => {
                setDateTo(e.value);
              }}
              stylingMode={'outlined'}
            />
          </ToolbarItem>
          <ToolbarItem location="after" locateInMenu="auto">
            <Button
              text="Exportar"
              icon="fas fa-file-excel"
              stylingMode="outlined"
              type={'success'}
              onClick={() => {
                window.open(
                  `/kardex/export?dateFrom=${DateUtils.dateFormatter(dateFrom)}&dateTo=${DateUtils.dateFormatter(dateTo)}`,
                  '_blank',
                );
              }}
            />
          </ToolbarItem>
          <ToolbarItem location={'after'} locateInMenu={'auto'}>
            <Button
              text="Kardex"
              icon="fas fa-file-excel"
              stylingMode="outlined"
              type={'success'}
              onClick={() => {
                const productId = gridRef.current?.instance().getSelectedRowKeys()[0];
                if (!productId) {
                  return;
                }
                window.open(
                  `/kardex/${productId}/export?dateFrom=${DateUtils.dateFormatter(dateFrom)}&dateTo=${DateUtils.dateFormatter(dateTo)}`,
                  '_blank',
                );
              }}
            />
          </ToolbarItem>
          <ToolbarItem location="after" locateInMenu="auto" widget="dxButton" showText="inMenu">
            <Button text="Refresh" icon="refresh" stylingMode="text" onClick={refetch} />
          </ToolbarItem>
        </Toolbar>

        {isLoading && (
          <div className="kardex-loading" role="status" aria-label="Cargando Kardex">
            <LoadIndicator visible={true} height={40} width={40} />
          </div>
        )}

        {isError && !isLoading && (
          <div className="kardex-error" role="alert">
            <span>{errorMessage}</span>
            <Button text="Reintentar" icon="refresh" stylingMode="outlined" onClick={refetch} />
          </div>
        )}

        {!isLoading && !isError && rows.length === 0 && (
          <div className="kardex-empty" role="status">
            No hay registros para el rango de fechas seleccionado
          </div>
        )}

        {!isLoading && (
          <DataGrid ref={gridRef} dataSource={rows} keyExpr={'producto_id'}>
            <Selection selectAllMode="allPages" showCheckBoxesMode="onClick" mode="single" />
            <Column dataField={'producto'} caption={'Producto'} />
            <Column dataField={'saldo'} caption={'Saldo'} alignment={'right'} />
            <Column dataField={'ingresos'} caption={'Ingresos'} alignment={'right'} />
            <Column dataField={'salidas'} caption={'Salidas'} alignment={'right'} />
            <Column dataField={'stock'} caption={'Stock '} alignment={'right'} />

            <FilterRow visible={true} />

            <MasterDetail enabled={true} render={renderDetail} />
          </DataGrid>
        )}
      </div>
    </ScrollView>
  );
};
