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
import {Field} from "../../core/field/Field";
import NumberUtils from "../../core/utils/NumberUtils";
import {useNavigate} from "react-router-dom";

export const CashboxPage = () => {
    const container = "wrapper";
    const gridRef = useRef<DxDataGridEvents>(null);
    const [url, setUrl] = useState<string | undefined>(undefined);
    const [cashboxId, setCashboxId] = useState<string | undefined>(undefined);
    const [cashboxBalance, setCashboxBalance] = useState<number | undefined>(undefined);
    const fieldsRef = useRef<Field[]>()
    const navigate = useNavigate();

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
                                message: 'Caja cerrada correctamente',
                                type: 'success',
                                displayTime: 3000,
                            }, {position: 'top right'})
                            setCashboxId(undefined);
                            setCashboxBalance(undefined);
                            setUrl(undefined);
                        });
                    }
                })
            }
        }
    ]

    const checkCashbox = () => {
        httpClient.get<any>('cashbox-new/last')
            .subscribe((response) => {
                if (!response.data) {
                    openCashbox();
                    return;
                }
                setupCashbox(response.data);
            });
    }

    const openCashbox = () => {
        let amount = prompt("Ingrese el monto inicial de la caja");
        if (amount) {
            httpClient.post<any>('cashbox-new/open', {amount: amount})
                .subscribe((response) => {
                    notify({
                        message: 'Caja abierta correctamente',
                        type: 'success',
                        displayTime: 3000,
                    }, {position: 'top right'})
                    setupCashbox(response.data);
                });
        } else {
            navigate('/');
        }
    }

    const setupCashbox = (cashbox: any) => {
        setUrl(Constants.API_BASE_URL + 'cashboxes/' + cashbox.caja_id + '/details');
        fieldsRef.current = CashboxDetailFields(cashbox.caja_id);
        setCashboxId(cashbox.caja_id);
        setCashboxBalance(cashbox.balance);
    }

    useEffect(() => {
        checkCashbox();
        console.log('cashboxId', cashboxId)
    }, [cashboxId]);

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
                        column: 'monto',
                        summaryType: 'custom',
                        customizeText: (e) => {
                            return 'Balance: ' + NumberUtils.formatCurrency(cashboxBalance ?? 0);
                        }
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
