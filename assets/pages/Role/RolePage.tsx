import "./RolePage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {RoleFields} from "./RoleFields";
import {useEffect, useRef} from "react";
import notify from "devextreme/ui/notify";
import TreeView from "devextreme-react/tree-view";
import {bus} from "../../core/event/EventBus";
import {httpClient} from "../../core/http/HttpClient";
import DataSource from "devextreme/data/data_source";
import ArrayStore from "devextreme/data/array_store";

export const RolePage = () => {
    const container = "wrapper";
    const fields = RoleFields();
    const gridRef = useRef<DxDataGridEvents>(null);
    const treeRef = useRef<TreeView>(null);
    const permissionsData = useRef<DataSource>(new DataSource({
        store: new ArrayStore({
            key: 'id',
            data: []
        })
    }))

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

    const PERMISSION_EVENTS = {
        ADD: 'permission:add',
        EDIT: 'permission:edit',
        DELETE: 'permission:delete',
        SAVE: 'permission:save',
        CANCEL: 'permission:cancel',
        SUCCESS: 'permission:success',
        LOADING: 'permission:loading',
    }

    const toolbar = [
        {
            text: 'Permisos',
            icon: 'fas fa-user-shield fa-1x',
            type: 'info',
            onClick: () => {
                let row = gridRef.current?.getSelectedRow();
                if (row) {
                    bus.emit(PERMISSION_EVENTS.ADD, {title: 'Permisos de ' + row.descripcion});
                    httpClient.get<any>('role-menu/get?format=dx', {params: {roleId: row.rol_id}})
                        .subscribe(response => {
                            let store = permissionsData.current.store() as ArrayStore;
                            store.clear();
                            response.data.forEach((item: any) => {
                                store.insert(item);
                            })
                            permissionsData.current.reload();
                        })
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

    let alreadySetup = false;

    const setupEvents = () => {
        bus.on(PERMISSION_EVENTS.SAVE, () => {
            let row = gridRef.current?.getSelectedRow();
            if (row) {
                let data = treeRef.current?.instance.getSelectedNodes();
                let permissions: any[] = [];
                data?.forEach(item => {
                    permissions.push(item.parent?.key);
                    permissions.push(item.key);
                })
                permissions = [...new Set(permissions)];
                httpClient.post<any>('role-menu/add', {roleId: row.rol_id, permissions: permissions})
                    .subscribe(response => {
                        notify({
                            message: response.data.msg,
                            type: 'success',
                            displayTime: 3000,
                        }, {position: 'top right'})
                        bus.emit(PERMISSION_EVENTS.SUCCESS);
                    })
            }
        })
    }

    useEffect(() => {
        if (!alreadySetup) {
            setupEvents();
            alreadySetup = true;
        }
    }, [])

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
            <DxDialog
                events={PERMISSION_EVENTS}
            >
                <TreeView
                    ref={treeRef}
                    dataSource={permissionsData.current}
                    showCheckBoxesMode={'normal'}
                    selectByClick={true}
                />
            </DxDialog>
        </div>
    );
};
