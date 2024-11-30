import {DataGrid} from "devextreme-react";
import {dxUtils} from "../common/DxUtils";
import CustomStore from "devextreme/data/custom_store";
import {useEffect, useState} from "react";
import {buildColumns, getIdField} from "./DxDataGridUtils";
import {Paging} from "devextreme-react/data-grid";

type DxDetailDataGridProps = {
    options: any;
}

export const DxDetailDataGrid = ({options}: DxDetailDataGridProps) => {
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
                <Paging enabled={false}/>
            </DataGrid>
        </div>
    );
}
