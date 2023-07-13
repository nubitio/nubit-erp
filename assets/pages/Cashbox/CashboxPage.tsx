import "./CashboxPage.scss";
import {DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {CashboxDetailFields} from "./CashboxFields";
import {useEffect, useRef, useState} from "react";
import {httpClient} from "../../core/http/HttpClient";
import {confirm} from "devextreme/ui/dialog";
import notify from "devextreme/ui/notify";

export const CashboxPage = () => {
    const container = "wrapper";
    const fields = CashboxDetailFields();
    const gridRef = useRef<DxDataGridEvents>(null);
    const [url, setUrl] = useState<string | undefined>(undefined);
    const [cashboxId, setCashboxId] = useState<string | undefined>(undefined);

    const API_URL = Constants.API_BASE_URL + 'cashboxes';

    const EVENTS = {
        ADD: 'cashbox:add',
        EDIT: 'cashbox:edit',
        DELETE: 'cashbox:delete',
        SAVE: 'cashbox:save',
        CANCEL: 'cashbox:cancel',
        SUCCESS: 'cashbox:success',
        LOADING: 'cashbox:loading',
    }

    const toolbar = [
        {
            text: 'Cerrar Caja',
            icon: 'close',
            onClick: () => {
                confirm('¿Está seguro que desea cerrar la caja?', 'Cerrar Caja').then((result) => {
                    if (result) {
                        httpClient.post<any>(`cashbox/close/${cashboxId}`, {}).subscribe((response) => {
                            notify({
                                message: response.data.message,
                                type: 'success',
                                displayTime: 3000,
                            }, {position: 'top right'})
                            checkCashbox();
                        });
                    }
                })
            }
        }
    ]

    const checkCashbox = () => {
        httpClient.get<any>('cashbox/last').subscribe((response) => {
            console.log(response.data);
            if (response.data) {
                setUrl(API_URL + '/' + response.data.caja_id + '/details');
                gridRef.current?.refresh();
            } else {
                let amount = prompt("Ingrese el monto inicial de la caja");
                if (amount) {
                    httpClient.post<any>('cashbox/open', {amount: amount}).subscribe((response) => {
                        setUrl(API_URL + '/' + response.data.caja_id + '/details');
                        setCashboxId(response.data.caja_id);
                        gridRef.current?.refresh();
                    });
                }
            }
        })
    }

    let alreadyChecked = false;

    useEffect(() => {
        if (!alreadyChecked) {
            alreadyChecked = true;
            checkCashbox();
        }
    }, []);

    const render = () => {
        return (
            <div>
                <DxDataGrid
                    ref={gridRef}
                    title={'Cashbox'}
                    toolbar={toolbar}
                    url={url!}
                    allowAdd={true}
                    allowEdit={true}
                    allowDelete={true}
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
        )
    }

    return (
        <div id={container}>
            {url ? render() : null}
        </div>
    );
};
