import "./KardexPage.scss";
import React, {useCallback, useEffect, useRef, useState} from 'react';

import {Item as ToolbarItem, Toolbar} from 'devextreme-react/toolbar';
import Button from 'devextreme-react/button';
import ScrollView from 'devextreme-react/scroll-view';
import DateBox from "devextreme-react/date-box";
import {kardexService} from "../../services/kardex.service";
import DataGrid, {Column, FilterRow, MasterDetail, Selection} from "devextreme-react/data-grid";
import {DateUtils} from "../../core/utils/DateUtils";
import {KardexDetailPage} from "./KardexDetailPage";

export const KardexPage = () => {
    const gridRef = useRef<DataGrid>(null)
    const [dateFrom, setDateFrom] = useState<Date>(new Date());
    const [dateTo, setDateTo] = useState<Date>(new Date());
    const [data, setData] = useState<any[]>([]);

    const loadData = useCallback(() => {
        kardexService.getAll(DateUtils.dateFormatter(dateFrom), DateUtils.dateFormatter(dateTo)).subscribe((response) => {
            console.log(response.data)
            setData(response.data)
        })
    }, []);

    const refresh = useCallback(() => {
        loadData();
    }, [loadData]);

    useEffect(() => {
        loadData();
    }, [dateFrom, dateTo]);

    return (
        <ScrollView className='view-wrapper-scroll'>
            <div className='view-wrapper view-wrapper-details'>
                <Toolbar className='toolbar-details'>
                    <ToolbarItem location='before'>
                        <Button icon='arrowleft' onClick={() => window.history.back()}/>
                    </ToolbarItem>
                    <ToolbarItem location='before' text='Kardex'/>
                    <ToolbarItem location='after' locateInMenu='auto'>
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
                    <ToolbarItem location='after' locateInMenu='auto'>
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
                    <ToolbarItem location='after' locateInMenu='auto'>
                        <Button
                            text='Exportar'
                            icon='fas fa-file-excel'
                            stylingMode='outlined'
                            type={'success'}
                            onClick={() => {
                                window.open(`/kardex/export?dateFrom=${DateUtils.dateFormatter(dateFrom)}&dateTo=${DateUtils.dateFormatter(dateTo)}`, '_blank');
                            }}
                        />
                    </ToolbarItem>
                    <ToolbarItem location={'after'} locateInMenu={'auto'}>
                        <Button
                            text='Kardex'
                            icon='fas fa-file-excel'
                            stylingMode='outlined'
                            type={'success'}
                            onClick={() => {
                                const productId = gridRef.current?.instance.getSelectedRowKeys()[0];
                                if (!productId) {
                                    return;
                                }
                                window.open(`/kardex/${productId}/export?dateFrom=${DateUtils.dateFormatter(dateFrom)}&dateTo=${DateUtils.dateFormatter(dateTo)}`, '_blank');
                            }}
                        />
                    </ToolbarItem>
                    <ToolbarItem
                        location='after'
                        locateInMenu='auto'
                        widget='dxButton'
                        showText='inMenu'
                    >
                        <Button
                            text='Refresh'
                            icon='refresh'
                            stylingMode='text'
                            onClick={refresh}
                        />
                    </ToolbarItem>
                </Toolbar>
                <DataGrid
                    ref={gridRef}
                    dataSource={data}
                    keyExpr={'producto_id'}
                >
                    <Selection
                        selectAllMode='allPages'
                        showCheckBoxesMode='onClick'
                        mode='single'
                    />
                    <Column dataField={'producto'} caption={'Producto'}/>
                    <Column dataField={'saldo'} caption={'Saldo'} alignment={'right'}/>
                    <Column dataField={'ingresos'} caption={'Ingresos'} alignment={'right'}/>
                    <Column dataField={'salidas'} caption={'Salidas'} alignment={'right'}/>
                    <Column dataField={'stock'} caption={'Stock '} alignment={'right'}/>

                    <FilterRow visible={true}/>

                    <MasterDetail enabled={true} component={KardexDetailPage}/>
                </DataGrid>
            </div>
        </ScrollView>
    );
};
