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

/*
CREATE DEFINER=`c3_hmr`@`%` PROCEDURE `setup_menu`()
BEGIN
	SET FOREIGN_key_checks = 0;
	TRUNCATE menu;
	INSERT INTO menu ( menu_id, orden, descripcion, link, icon )
	VALUES
		( 1, 1, 'Dashboard', 'dashboard', 'fas fa-tachometer-alt' );
	INSERT INTO menu ( menu_id, orden, descripcion, icon )
	VALUES
		( 100, 1, 'Articulos/Servicios', 'fas fa-inbox' ),
		( 200, 2, 'Ventas', 'fas fa-shopping-cart' ),
		( 300, 3, 'Caja', 'fas fa-money-check-alt' ),
		( 400, 4, 'Pacientes', 'fas fa-money-check-alt' ),
		( 500, 5, 'Historia', 'fas fa-house-medical' ),
		( 600, 6, 'RRHH', 'fas fa-users-cog' ),
		( 700, 7, 'Seguridad', 'fas fa-shield-alt' ),
		( 1000, 1000, 'Sistema', 'fas fa-cog' ),
		( 2000, 2000, 'Developer', 'fas fa-code' );
	INSERT INTO menu ( menu_id, orden, descripcion, link, icon, parent_id )
	VALUES
	-- almacen
	( 101, 1, 'Informacion', 'product', 'fas fa-cube', 100 ),
	( 102, 2, 'Tarifas', 'price-list', 'fas fa-money', 100 ),
	-- ventas
	( 201, 1, 'Registrar ventas', 'sale-hms', 'fas fa-table', 200 ),
	( 202, 2, 'Reporte de ventas', 'sale-consult', 'fas fa-table', 200 ),
	-- caja
	( 301, 1, 'Registro caja', 'cashbox', 'fas fa-table', 300 ),
	( 302, 2, 'Consulta caja', 'cashbox-consult', 'fas fa-table', 300 ),
	-- caja
	( 401, 1, 'Registro de pacientes', 'military-patient', 'fas fa-table', 400 ),
	( 402, 2, 'Tipos de pacientes', 'military-patient', 'fas fa-table', 400 ),
	-- Historia
	( 501, 1, 'Citas', 'consultation', 'fas fa-table', 500 ),
	( 502, 2, 'Reportes', 'sale-consult', 'fas fa-table', 500 ),
	-- rrhh
	( 601, 1, 'Empleados', 'employee', 'fas fa-table', 600 ),
	( 602, 2, 'Cargos', 'position', 'fas fa-table', 600 ),
	( 603, 3, 'Áreas', 'department', 'fas fa-table', 600 ),
	( 604, 4, 'Especialidades', 'specialty', 'fas fa-table', 600 ),
	( 605, 5, 'Medicos', 'physician', 'fas fa-table', 600 ),
	-- seguridad
	( 701, 1, 'Usuarios', 'user', 'fas fa-user', 700 ),
	( 702, 2, 'Roles', 'role', 'fas fa-tag', 700 ),
	-- sistema
	( 1001, 1, 'Empresas', 'company', 'fas fa-building', 1000 ),
	( 1002, 2, 'Locales', 'branch', 'fas fa-home', 1000 ),
	( 1003, 3, 'Almacenes', 'warehouse', 'fas fa-table', 1000 ),
	( 1004, 4, 'Recetas', 'recipe', 'fas fa-table', 1000 ),
	( 1005, 5, 'Performance', 'performance', 'fas fa-table', 1000 ),
	( 1006, 6, 'Categorias', 'category', 'fas fa-tags', 1000 ),
	( 1008, 8, 'Transacciones', 'operation', 'fas fa-retweet', 1000 ),
	( 1009, 9, 'Tipo comprobante', 'voucher-type', 'fas fa-receipt', 1000 ),
	( 1010, 10, 'Local comprobante', 'voucher-series', 'fas fa-file-invoice', 1000 ),
	-- developer
	( 2001, 1, 'Configuración', 'config', 'fas fa-cog', 2000 );
	SET FOREIGN_key_checks = 1;
END
*/
