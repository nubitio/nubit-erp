import "./ConsultationPage.scss";
import {Constants} from "../../../../core/common/Constants";
import {DxDataGrid, DxDataGridEvents} from "../../../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../../../core/dialog/DxDialog";
import {DxForm, DxFormEvents} from "../../../../core/form/DxForm";
import {ConsultationFields} from "./ConsultationFields";
import {useEffect, useRef} from "react";
import notify from "devextreme/ui/notify";
import {useNavigate} from "react-router";

export const ConsultationPage = () => {
    const gridRef = useRef<DxDataGridEvents|null>(null);
    const container = "wrapper";
    const fields = ConsultationFields();
    const formRef = useRef<DxFormEvents | any>(null);


    const navigate = useNavigate();
    const API_URL = Constants.API_BASE_URL + 'consultations';

    const EVENTS = {
        ADD: 'consultation:add',
        EDIT: 'consultation:edit',
        DELETE: 'consultation:delete',
        SAVE: 'consultation:save',
        CANCEL: 'consultation:cancel',
        SUCCESS: 'consultation:success',
        LOADING: 'consultation:loading',
    }
    const toolbar = [
        {
            text: 'Pagos',
            icon: 'fas fa-receipt fa-1x',
            type: 'info',
            onClick: (e: any) => {
                const selectedRow = gridRef.current?.getSelectedRow();
                if (!selectedRow){
                    notify({
                        message: 'Selecciona una consulta a enlazar',
                        type: 'warning',
                        displayTime: 3000,
                    }, {position: 'top right'})
                    return;
                }
                navigate('/consultation-sale/' + selectedRow.id);
            }
        }
    ]

    return (
        <div id={container}>
            <DxDataGrid
                ref={gridRef}
                title={'Consultas médicas'}
                url={API_URL}
                allowAdd={true}
                allowEdit={true}
                allowDelete={true}
                fields={fields}
                events={EVENTS}
                toolbar={toolbar}
            />
            <DxDialog
                events={EVENTS}
            >
                <DxForm
                    url={API_URL}
                    fields={fields}
                    events={EVENTS}
                />
            </DxDialog>
        </div>
    );
};
