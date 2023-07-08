import "./SalePage.scss";
import {DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {DxForm} from "../../core/form/DxForm";
import {DxDialog} from "../../core/dialog/DxDialog";
import {Constants} from "../../core/common/Constants";
import {SaleDetailFields, SaleFields} from "./SaleFields";
import {useRef} from "react";

export const SalePage = () => {
    const container = "wrapper";
    const fields = SaleFields();
    const detailFields = SaleDetailFields();
    const gridRef = useRef<DxDataGridEvents>(null);

    const API_URL = Constants.API_BASE_URL + 'sales';

    const EVENTS = {
        ADD: 'sale:add',
        EDIT: 'sale:edit',
        DELETE: 'sale:delete',
        SAVE: 'sale:save',
        CANCEL: 'sale:cancel',
        SUCCESS: 'sale:success',
        LOADING: 'sale:loading',
    }

    const toolbar = [
        {
            text: 'Vista previa',
            icon: 'fas fa-eye fa-1x text-info',
            onClick: () => {
                console.log('Vista previa');
            }
        },
        {
            text: 'Imprimir',
            icon: 'fas fa-print fa-1x text-primary',
            onClick: () => {
                console.log('Imprimir');
            }
        },
        {
            text: 'Anular',
            icon: 'fas fa-ban fa-1x text-danger',
            onClick: () => {
                console.log('Anular');
            }
        },
        {
            text: 'Generar comprobante',
            icon: 'fas fa-file-invoice fa-1x text-info',
            onClick: () => {
                console.log('Generar comprobante');
                gridRef.current?.sayHi();
            }
        }
    ]

    return (
        <div id={container}>
            <DxDataGrid
                ref={gridRef}
                title={'Ventas'}
                url={API_URL}
                fields={fields}
                allowAdd={true}
                toolbar={toolbar}
                detailUrl={API_URL + '/{id}/details'}
                detailFields={detailFields}
                events={EVENTS}
                sort={[
                    {selector: 'date_created', desc: true}
                ]}
            />
            <DxDialog options={{
                width: 900,
                height: 700,
                events: EVENTS
            }}>
                <DxForm options={{
                    url: API_URL,
                    fields: fields,
                    detailFields: detailFields,
                    events: EVENTS,
                }}/>
            </DxDialog>
        </div>
    );
};
