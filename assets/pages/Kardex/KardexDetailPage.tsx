import "./KardexPage.scss";
import React, {useCallback, useEffect, useState} from 'react';
import ScrollView from 'devextreme-react/scroll-view';
import {kardexService} from "../../services/kardex.service";
import DataGrid, {Column} from "devextreme-react/data-grid";
import {DateUtils} from "../../core/utils/DateUtils";

export const KardexDetailPage = (props: any) => {
    const [dateFrom, setDateFrom] = useState<Date>(new Date());
    const [dateTo, setDateTo] = useState<Date>(new Date());
    const [data, setData] = useState<any[]>([]);

    const loadData = useCallback(() => {
        kardexService.getDetail(props.data.key, DateUtils.dateFormatter(dateFrom), DateUtils.dateFormatter(dateTo)).subscribe((response) => {
            console.log(response.data)
            setData(response.data)
        })
    }, []);

    useEffect(() => {
        loadData();
    }, []);

    return (
        <ScrollView className='view-wrapper-scroll'>
            <DataGrid
                dataSource={data}
            >
                <Column dataField={'fecha'} caption={'Fecha'}/>
                <Column dataField={'tipo'} caption={'Tipo'} alignment={'center'}/>
                <Column dataField={'operacion'} caption={'Operación'}/>
                <Column dataField={'cantidad'} caption={'Cantidad'} alignment={'center'}/>
            </DataGrid>
        </ScrollView>
    );
};
