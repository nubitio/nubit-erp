import "./RolePage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {RoleFields} from "./RoleFields";
import {bus} from "../../core/event/EventBus";
import {useRef} from "react";
import notify from "devextreme/ui/notify";

export const RolePage = () => {
    const container = "wrapper";
    const fields = RoleFields();
    const gridRef = useRef<DxDataGridEvents>(null);

    const API_URL = Constants.API_BASE_URL + 'roles';

    const EVENTS = {
        ADD: 'role:add',
        EDIT: 'role:edit',
        DELETE: 'role:delete',
        SAVE: 'role:save',
        CANCEL: 'role:cancel',
        SUCCESS: 'role:success',
        LOADING: 'role:loading',
    }

    const toolbar = [
        {
            text: 'Permisos',
            icon: 'fas fa-user-shield fa-1x',
            onClick: () => {
                let row = gridRef.current?.getSelectedRow();
                if (row) {
                    bus.emit('permission:load', row);
                } else {
                    notify({
                        message: 'Debe seleccionar un rol',
                        type: 'error',
                        displayTime: 3000,
                    }, {position: 'top right'})
                }
            }
        }
    ]

    return (
        <div id={container}>
            <DxDataGrid
                ref={gridRef}
                title={'Roles'}
                url={API_URL}
                allowAdd={true}
                allowEdit={true}
                allowDelete={true}
                toolbar={toolbar}
                fields={fields}
                events={EVENTS}
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
