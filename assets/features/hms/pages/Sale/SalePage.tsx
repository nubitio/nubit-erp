import "./SalePage.scss";
import {DATA_GRID_EVENTS, DxDataGrid, DxDataGridEvents} from "../../../../core/datagrid/DxDataGrid";
import {DxForm, DxFormEvents, FORM_EVENTS} from "../../../../core/form/DxForm";
import {DxDialog} from "../../../../core/dialog/DxDialog";
import {Constants} from "../../../../core/common/Constants";
import {SaleDetailFields, SaleFields, setTotal} from "./SaleFields";
import {useEffect, useRef, useState} from "react";
import {DiscountDialog, DiscountDialogEvent} from "../../../../components/discount-dialog/DiscountDialog";
import {bus} from "../../../../core/event/EventBus";
import {saleService} from "../../../../services/sale.service";
import notify from "devextreme/ui/notify";
import * as qz from 'qz-tray';
import {httpClient} from "../../../../core/http/HttpClient";
import {userInformation} from "../../../../core/common/UserInformation";
import {CustomerPage} from "../../../../pages/Customer/CustomerPage";
import {Setting, settings} from "../../../../Settings";
import {MilitaryPatientPage} from "../MilitaryPatient/MilitaryPatientPage";
import {MilitaryPatientFields} from "../MilitaryPatient/MilitaryPatientFields";
import {SalePrintPreviewPopup} from "../../../../pages/Sale/SalePrintPreviewPopup";

export const SalePage = () => {
    const gridRef = useRef<DxDataGridEvents>(null);
    const formRef = useRef<DxFormEvents>(null);
    const patientFormRef = useRef<DxFormEvents>(null);
    const [printerName, setPrinterName] = useState<string | undefined>(undefined);
    const [printEnabled, setPrintEnabled] = useState<boolean>(false);
    const [cloneEnabled, setCloneEnabled] = useState<boolean>(false);
    const selectedSaleId = useRef<string | undefined>(undefined);
    const [isPreviewVisible, setIsPreviewVisible] = useState<boolean>(false);

    const container = "wrapper";
    const fields = SaleFields(formRef.current);
    const detailFields = SaleDetailFields(formRef.current);

    const API_URL = Constants.API_BASE_URL + 'sale-hms';

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

        bus.on(DATA_GRID_EVENTS.SELECTION_CHANGED, (e: any) => {
            if (e.selectedRowKeys.length === 1) {
                setPrintEnabled(true);
                setCloneEnabled(true);
            } else {
                setPrintEnabled(false);
                setCloneEnabled(false);
            }
        })

        bus.on(EVENTS.SUCCESS, (payload: any) => {
            settings.get(Setting.AUTO_INVOICE).then((setting) => {
                console.log(setting)
                if (setting?.value === 'true') {
                    makeInvoice(payload.data.movimiento_id);
                }
            })
        })

        bus.on(CustomerPage.EVENTS.SUCCESS, (payload: any) => {
            formRef.current?.setFieldValue('cliente', payload.data);
            console.log('El payload es: ', payload.data);
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

    const toolbar = [
        {
            text: 'Vista previa',
            icon: 'fas fa-eye fa-1x',
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
            icon: 'fas fa-print fa-1x',
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
            icon: 'fas fa-ban fa-1x',
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
            icon: 'fas fa-file-invoice fa-1x',
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
            icon: 'fas fa-copy fa-1x',
            type: 'info',
            onClick: () => {
                let saleId = gridRef.current?.getSelectedRowKey();
                saleService.getOne(saleId).subscribe((response) => {
                    delete response.data.movimiento_id;
                    bus.emit(EVENTS.ADD, response.data);
                });
            },
            disabled: !cloneEnabled
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
                filter={[
                    {
                        field: 'a.local',
                        operator: '=',
                        value: userInformation().currentBranchId
                    }
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
            <DxDialog
                events={MilitaryPatientPage.EVENTS}
            >
                <DxForm
                    ref={patientFormRef}
                    url={MilitaryPatientPage.API_URL}
                    fields={MilitaryPatientFields(patientFormRef.current)}
                    events={MilitaryPatientPage.EVENTS}
                />
            </DxDialog>
            <SalePrintPreviewPopup
                saleId={selectedSaleId.current}
                visible={isPreviewVisible}
                onPositive={() => {
                    setIsPreviewVisible(false);
                    window.open(`/sale/print/${selectedSaleId.current}`, '_blank')
                }}
                onNegative={() => {
                    setIsPreviewVisible(false)
                }}
            />
        </div>
    );
};
