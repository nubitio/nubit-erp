import "./SalePage.scss";
import {DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {DxForm, DxFormEvents, FORM_EVENTS} from "../../core/form/DxForm";
import {DxDialog} from "../../core/dialog/DxDialog";
import {Constants} from "../../core/common/Constants";
import {SaleDetailFields, SaleFields, setTotal} from "./SaleFields";
import {useEffect, useRef, useState} from "react";
import {DiscountDialog, DiscountDialogEvent} from "../../components/discount-dialog/DiscountDialog";
import {bus} from "../../core/event/EventBus";
import {saleService} from "../../services/sale.service";
import notify from "devextreme/ui/notify";
import * as qz from 'qz-tray';
import {httpClient} from "../../core/http/HttpClient";

export const SalePage = () => {
    const gridRef = useRef<DxDataGridEvents>(null);
    const formRef = useRef<DxFormEvents>(null);
    const [printerName, setPrinterName] = useState<string | undefined>(undefined);

    const container = "wrapper";
    const fields = SaleFields(formRef.current);
    const detailFields = SaleDetailFields(formRef.current);

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

    const setupObservers = () => {
        bus.on<number>(DiscountDialogEvent.APPLY, (discountPercent: number) => {
            let discount = Number(discountPercent * 100).toFixed(2);
            formRef.current?.setFieldValue('descuento', Number(discount));
        })

        bus.on(FORM_EVENTS.ROW_ADDED, () => {
            setTotal(formRef.current);
        })

        bus.on(FORM_EVENTS.ROW_UPDATED, () => {
            setTotal(formRef.current);
        })

        bus.on(FORM_EVENTS.ROW_REMOVED, () => {
            setTotal(formRef.current);
        })
    }

    const setupPrinter = () => {
        qz.websocket.connect().then(() => {
            qz.printers.getDefault().then((data: string) => {
                setPrinterName(data);
                console.log('Impresora: ' + data);
            })
        }).catch((err: any) => {
            console.error(err);
        })
    }

    let alreadySetup = false;

    useEffect(() => {
        if (!alreadySetup) {
            setupObservers();
            setupPrinter();
            alreadySetup = true;
        }
    }, []);

    const voidSale = (saleId: string) => {
        let reason = prompt('Motivo de anulación', '')
        if (reason === null) {
            return;
        }
        gridRef.current?.showLoading('Anulando comprobante...')
        saleService.voidSale(saleId, reason).subscribe((response) => {
            notify({
                    message: response.data.message,
                    type: 'success',
                    displayTime: 3000,
                }, {position: 'top right'}
            );
            gridRef.current?.refresh();
        }).add(() => {
            gridRef.current?.hideLoading();
        });
    }

    const makeInvoice = (saleId: string) => {
        gridRef.current?.showLoading('Generando comprobante...')
        saleService.makeInvoice(saleId).subscribe((response) => {
            notify({
                    message: response.data.message,
                    type: 'success',
                    displayTime: 3000,
                }, {position: 'top right'}
            );
            gridRef.current?.refresh();
            print(saleId);
        }).add(() => {
            gridRef.current?.hideLoading();
        });
    }

    const print = (saleId: string) => {
        let config = qz.configs.create(printerName);
        httpClient.get<any>('/qz/print/' + saleId).subscribe((response) => {
            qz.print(config, [response.data]).catch((err: any) => {
                console.error(err);
            })
        })
    }

    const printPreview = (saleId: string) => {
        let url = 'sale/print/' + saleId;
        window.open(url + '?preview=true', '_blank');
    }

    const toolbar = [
        {
            text: 'Vista previa',
            icon: 'fas fa-eye fa-1x text-info',
            onClick: () => {
                let saleId = gridRef.current?.getSelectedRowKey();
                if (saleId) {
                    printPreview(saleId);
                } else {
                    notify({
                            message: 'Debe seleccionar una venta',
                            type: 'warning',
                            displayTime: 3000,
                        }, {position: 'top right'}
                    );
                }
            }
        },
        {
            text: 'Imprimir',
            icon: 'fas fa-print fa-1x text-primary',
            onClick: () => {
                let saleId = gridRef.current?.getSelectedRowKey();
                if (saleId) {
                    print(saleId);
                } else {
                    notify({
                            message: 'Debe seleccionar una venta',
                            type: 'warning',
                            displayTime: 3000,
                        }, {position: 'top right'}
                    );
                }
            }
        },
        {
            text: 'Anular',
            icon: 'fas fa-ban fa-1x text-danger',
            onClick: () => {
                let saleId = gridRef.current?.getSelectedRowKey();
                if (saleId) {
                    voidSale(saleId);
                } else {
                    notify({
                            message: 'Debe seleccionar una venta',
                            type: 'warning',
                            displayTime: 3000,
                        }, {position: 'top right'}
                    );
                }
            }
        },
        {
            text: 'Generar comprobante',
            icon: 'fas fa-file-invoice fa-1x text-info',
            onClick: () => {
                let saleId = gridRef.current?.getSelectedRowKey();
                if (saleId) {
                    makeInvoice(saleId);
                } else {
                    notify({
                            message: 'Debe seleccionar una venta',
                            type: 'warning',
                            displayTime: 2000,
                        }, {position: 'top right'}
                    );
                }
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
            <DxDialog
                width={900}
                height={620}
                events={EVENTS}
            >
                <DxForm
                    ref={formRef}
                    url={API_URL}
                    fields={fields}
                    detailFields={detailFields}
                    events={EVENTS}
                />
            </DxDialog>
            <DiscountDialog/>
        </div>
    );
};
