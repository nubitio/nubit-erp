import "./CashboxPage.scss";
import {DATAGRID_EVENTS, DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {CashboxDetailFields} from "./CashboxFields";
import {useEffect, useRef, useState} from "react";
import {httpClient} from "../../core/http/HttpClient";
import {confirm} from "devextreme/ui/dialog";
import notify from "devextreme/ui/notify";
import {Field} from "../../core/field/Field";
import {bus} from "../../core/event/EventBus";

export const CashboxPage = () => {
    const container = "wrapper";
    const gridRef = useRef<DxDataGridEvents>(null);
    const [url, setUrl] = useState<string | undefined>(undefined);
    const [cashboxId, setCashboxId] = useState<string | undefined>(undefined);
    const fieldsRef = useRef<Field[]>()

    const API_URL = Constants.API_BASE_URL + 'cashbox-details';

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
            type: 'warning',
            onClick: () => {
                confirm('¿Está seguro que desea cerrar la caja?', 'Cerrar Caja').then((result) => {
                    if (result) {
                        httpClient.post<any>(`cashbox-new/close/${cashboxId}`, {}).subscribe((response) => {
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
        httpClient.get<any>('cashbox-new/last').subscribe((response) => {
            if (response.data) {
                setupCashbox(response.data.caja_id);
            } else {
                let amount = prompt("Ingrese el monto inicial de la caja");
                if (amount) {
                    httpClient.post<any>('cashbox-new/open', {amount: amount})
                        .subscribe((response) => setupCashbox(response.data.caja_id));
                }
            }
        });
    }

    const setupCashbox = (cashboxId: string) => {
        setUrl(Constants.API_BASE_URL + 'cashboxes/' + cashboxId + '/details');
        fieldsRef.current = CashboxDetailFields(cashboxId);
        setCashboxId(cashboxId);
        gridRef.current?.refresh();
    }

    const setupObservers = () => {
        bus.on(DATAGRID_EVENTS.SUMMARY, (payload: any) => {
            if (payload.summaryProcess === 'start') {
                payload.totalValue = 0;
            }
            if (payload.summaryProcess === 'calculate') {
                let value = payload.value.tipo_id === 1 ? payload.value.monto : payload.value.monto * -1;
                console.log(value)
                payload.totalValue += Number(value);
                console.log(payload.totalValue)
            }
            if (payload.summaryProcess === 'finalize') {
                payload.totalValue = payload.totalValue.toFixed(2);
            }
        })
    }

    let alreadyChecked = false;
    let alreadySetup = false;

    useEffect(() => {
        if (!alreadyChecked) {
            alreadyChecked = true;
            checkCashbox();
        }
        if (!alreadySetup) {
            alreadySetup = true;
            setupObservers();
        }
    }, []);

    const render = (fields) => {
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
                    summaryFields={[{
                        showInColumn: 'monto',
                        summaryType: 'custom',
                        valueFormat: 'currency',
                        displayFormat: 'Balance: {0}'
                    }]}
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
            {cashboxId ? render(fieldsRef.current) : null}
        </div>
    );
};
