import {DataGrid} from "devextreme-react";
import {dxUtils} from "../common/DxUtils";
import CustomStore from "devextreme/data/custom_store";
import {useEffect, useState} from "react";
import {buildColumns, getIdField} from "./DxDataGridUtils";

export const DxDetailDataGrid = ({options}: any) => {
    const [data, setData] = useState<CustomStore | null>(null);

    let alreadyLoaded = false;

    useEffect(() => {
        if (!alreadyLoaded) {
            setData(dxUtils.getDataStore(options.url, getIdField(options.fields)));
            alreadyLoaded = true;
        }
    }, []);

    return (
        <div className='grid-detail'>
            <DataGrid
                dataSource={data}
                className='grid'
                noDataText=''
            >
                {buildColumns(options.fields)}
            </DataGrid>
        </div>
    );
}
