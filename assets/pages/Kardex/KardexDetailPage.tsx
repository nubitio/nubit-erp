import './KardexPage.scss';
import React, { useCallback, useEffect, useState } from 'react';
import ScrollView from 'devextreme-react/scroll-view';
import { kardexService } from '../../features/kardex';
import DataGrid, { Column } from 'devextreme-react/data-grid';
import { DateUtils } from '../../shared/lib/DateUtils';

interface KardexDetailPageProps {
  data: any;
  dateFrom: Date;
  dateTo: Date;
}

export const KardexDetailPage = (props: KardexDetailPageProps) => {
  const [data, setData] = useState<any[]>([]);

  const loadData = useCallback(() => {
    kardexService
      .getDetail(
        props.data.producto_id,
        DateUtils.dateFormatter(props.dateFrom),
        DateUtils.dateFormatter(props.dateTo),
      )
      .subscribe((response) => {
        setData(response.data);
      });
  }, [props.dateFrom, props.dateTo, props.data.producto_id]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  return (
    <ScrollView className="view-wrapper-scroll">
      <DataGrid dataSource={data}>
        <Column dataField={'fecha'} caption={'Fecha'} />
        <Column dataField={'tipo'} caption={'Tipo'} alignment={'center'} />
        <Column dataField={'operacion'} caption={'Operación'} />
        <Column dataField={'cantidad'} caption={'Cantidad'} alignment={'center'} />
      </DataGrid>
    </ScrollView>
  );
};
