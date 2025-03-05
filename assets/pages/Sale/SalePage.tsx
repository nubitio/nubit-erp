import "./SalePage.scss";
import {DATA_GRID_EVENTS, DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {DxForm, DxFormEvents, FORM_EVENTS} from "../../core/form/DxForm";
import {DxDialog} from "../../core/dialog/DxDialog";
import {Constants} from "../../core/common/Constants";
import {SaleDetailFields, SaleFields, setTotal} from "./SaleFields";
import {useEffect, useRef, useState} from "react";
import {DiscountDialog, DiscountDialogEvent} from "../../components/discount-dialog/DiscountDialog";
import {saleService} from "../../service/sale.service";
import notify from "devextreme/ui/notify";
import * as qz from 'qz-tray';
import {CustomerPage} from "../Customer/CustomerPage";
import {CustomerFields} from "../Customer/CustomerFields";
import {Setting, settings} from "../../Settings";
import {SalePrintPreviewPopup} from "./SalePrintPreviewPopup";
import {useEvents} from "../../core/event/EventHook";
import {httpClient} from "../../core/http/HttpClient";
import {forkJoin, map, of, switchMap} from "rxjs";

export const SalePage = () => {
    const [on, emit] = useEvents();
    const gridRef = useRef<DxDataGridEvents>(null);
    const formRef = useRef<DxFormEvents>(null);
    const customerFormRef = useRef<DxFormEvents>(null);
    const [printerName, setPrinterName] = useState<string | undefined>(undefined);
    const [printEnabled, setPrintEnabled] = useState<boolean>(false);
    const [cloneEnabled, setCloneEnabled] = useState<boolean>(false);
    const [isPreviewVisible, setIsPreviewVisible] = useState<boolean>(false);
    const selectedSaleId = useRef<string | undefined>(undefined);

    const container = "wrapper";
    const fields = SaleFields(formRef.current);
    const detailFields = SaleDetailFields(formRef.current);

    const API_URL = Constants.API_BASE_URL + 'movements';

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
        on<number>(DiscountDialogEvent.APPLY, (discountPercent: number) => {
            let discount = Number(discountPercent * 100).toFixed(2);
            formRef.current?.setFieldValue('descuento', Number(discount));
        })

        on(FORM_EVENTS.ROW_ADDED, () => {
            setTotal(formRef.current);
        })

        on(FORM_EVENTS.ROW_UPDATED, () => {
            setTotal(formRef.current);
        })

        on(FORM_EVENTS.ROW_REMOVED, () => {
            setTotal(formRef.current);
        })

        on(DATA_GRID_EVENTS.SELECTION_CHANGED, (e: any) => {
            if (e.length === 1) {
                setPrintEnabled(true);
                setCloneEnabled(true);
            } else {
                setPrintEnabled(false);
                setCloneEnabled(false);
            }
        })

        on(EVENTS.SUCCESS, (payload: any) => {
            settings.get(Setting.AUTO_INVOICE).then((setting) => {
                console.log(setting)
                if (setting?.value === 'true') {
                    makeInvoice(payload.data.id);
                }
            })
        })

        on(CustomerPage.EVENTS.SUCCESS, (payload: any) => {
            formRef.current?.setFieldValue('cliente', payload.data);
        })
    }

    const setupPrinter = () => {
        qz.websocket.connect().then(() => {
            qz.printers.getDefault().then((data: string) => {
                setPrinterName(data);
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
                    type: response.data.success ? 'success' : 'error',
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
        saleService.printInvoice(saleId).subscribe((response) => {
            qz.print(config, [response.data]).catch((err: any) => {
                console.error(err);
            })
        })
    }

    const toolbar = [
        {
            text: 'Vista previa',
            type: 'info',
            onClick: () => {
                let saleId = gridRef.current?.getSelectedRowKey();
                if (saleId) {
                    selectedSaleId.current = saleId;
                    setIsPreviewVisible(true);
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
            type: 'primary',
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
            },
            disabled: !printEnabled
        },
        {
            text: 'Anular',
            type: 'danger',
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
            type: 'warning',
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
        },
        {
            text: 'Duplicar',
            type: 'info',
            onClick: () => {
                emit(FORM_EVENTS.LOADING, true)
                let saleId = gridRef.current?.getSelectedRowKey();
                httpClient.get<any>(Constants.API_BASE_URL + 'movements/' + saleId).pipe(
                    switchMap(saleResponse =>
                        forkJoin({
                            sale: of(saleResponse),
                            items: httpClient.get<any>(Constants.API_BASE_URL + 'movements/' + saleId + '/items')
                        })
                    ),
                    map(({sale, items}) => ({
                        ...sale.data,
                        items: items.data
                    }))
                ).subscribe({
                    next: (data) => {
                        emit(EVENTS.ADD, data)
                    },
                    complete: () => emit(FORM_EVENTS.LOADING, false)
                })
            },
            disabled: !cloneEnabled
        }
    ]

    return (
        <div id={container}>
            <DxDataGrid
                id={'sales'}
                ref={gridRef}
                title={'Ventas'}
                url={API_URL}
                fields={fields}
                allowAdd={true}
                toolbar={toolbar}
                detailUrl={API_URL + '/{id}/items'}
                detailFields={detailFields}
                events={EVENTS}
                sort={[
                    {selector: 'numero', desc: true}
                ]}
            />
            <DxDialog
                width={1080}
                height={720}
                events={EVENTS}
            >
                <DxForm
                    ref={formRef}
                    url={API_URL}
                    fields={fields}
                    detailFields={detailFields}
                    events={EVENTS}
                    useIRI={true}
                />
            </DxDialog>
            <DiscountDialog/>
            <DxDialog
                events={CustomerPage.EVENTS}
            >
                <DxForm
                    ref={customerFormRef}
                    url={CustomerPage.API_URL}
                    fields={CustomerFields(customerFormRef.current)}
                    events={CustomerPage.EVENTS}
                />
            </DxDialog>
            <SalePrintPreviewPopup
                saleId={selectedSaleId.current}
                visible={isPreviewVisible}
                onPositive={() => {
                    setIsPreviewVisible(false);
                    window.open(`/receipt/${selectedSaleId.current}/ticket`, '_blank');
                }}
                onNegative={() => {
                    setIsPreviewVisible(false);
                }}
            />
        </div>
    );
};
