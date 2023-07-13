import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import React from "react";

export class SharedUtils {
    static randomId(): string {
        return Math.random().toString(36).substring(2, 15);
    }

    static showMessage(title: string, message: string) {
        ($ as any).messager.show({
            title: title,
            msg: message
        });
    }

    static booleanFormatter(val: number) {
        if (Number(val) === 1) {
            return '<span class="badge rounded-pill bg-success">SI</span>';
        } else if (Number(val) === 0) {
            return '<span class="badge rounded-pill bg-danger">NO</span>';
        } else {
            return '';
        }
    }

    static configFormatter(val: string) {
        if (val == 'true') {
            return '<span class="badge rounded-pill bg-success">SI</span>';
        } else if (val == 'false') {
            return '<span class="badge rounded-pill bg-danger">NO</span>';
        } else if (Number(val) === 0) {
            return '<span class="badge bg-warning text-dark">NO</span>';
        } else {
            return '<span class="badge rounded-pill bg-dark">' + val + '</span>';
        }
    }

    static dxBooleanFormatter(cell: ColumnCellTemplateData) {
        if (Number(cell.value) === 1) {
            return <span className="badge rounded-pill bg-success">SI</span>;
        } else if (Number(cell.value) === 0) {
            return <span className="badge rounded-pill bg-danger">NO</span>;
        } else {
            return 'No boolean value';
        }
    }

    static dxStatusFormatter(cell: ColumnCellTemplateData) {
        if (Number(cell.data.estado) === 1) {
            return React.createElement('span', {className: 'badge rounded-pill bg-primary'}, 'ON');
        } else if (Number(cell.data.estado) === 2) {
            return React.createElement('span', {className: 'badge rounded-pill bg-secondary'}, 'OFF');
        } else if (Number(cell.data.estado) === 3) {
            return React.createElement('span', {className: 'badge rounded-pill bg-warning text-dark'}, 'Warning');
        } else if (Number(cell.data.estado) === 4) {
            return React.createElement('span', {className: 'badge rounded-pill bg-info text-dark'}, 'Observado');
        } else {
            return React.createElement('span', {className: 'badge rounded-pill bg-dark'}, 'Status no tracked');
        }
    }

    static dxVoidedFormatter(cell: ColumnCellTemplateData) {
        if (Number(cell.value) === 1) {
            return <span className="badge rounded-pill bg-success">NO</span>;
        } else if (Number(cell.value) === 2) {
            return <span className="badge rounded-pill bg-danger">SI</span>;
        } else {
            return null;
        }
    }

    static imageFormatter(image: string, name: string, path = '') {
        if (image) {
            return `<a href="${path}${image}" target="_blank">
                    <img src="${path}${image}" style="width: 30px; height: 30px;"  alt=""/>
                </a>`;
        } else {
            return `<a href="https://ui-avatars.com/api/?name=${name}" target="_blank" ">
                    <img src="https://ui-avatars.com/api/?name=${name}" style="width: 30px; height: 30px;"  alt=""/>
                </a>`;
        }
    }

    static dxImageFormatter(cell: ColumnCellTemplateData, column: any = 'imagen') {
        if (cell.data[column]) {
            return React.createElement('a', {
                href: cell.data[column].path,
                target: '_blank',
            }, React.createElement('img', {src: cell.data[column].path, style: {width: '30px', height: '30px'}}));
        } else {
            return React.createElement('a', {
                href: 'https://ui-avatars.com/api/?name=' + cell.data.descripcion,
                target: '_blank',
            }, React.createElement('img', {
                src: 'https://ui-avatars.com/api/?name=' + cell.data.descripcion,
                style: {width: '30px', height: '30px'}
            }));
        }
    }

    static voucherFormatter(value: string | null, row: any) {
        if (value !== null) {
            return '<a href="/sale/pdf/' + row['movimiento_id'] + '" class="text-reset" target="_blank"><i class="fas fa-receipt fa-lg"></i> ' + row['serie'] + '-' + row['numero'] + '</a>';
        } else {
            return '';
        }
    }

    static invoiceFormatter(value: any | null | undefined, row: any) {
        if (value !== null && value !== undefined) {
            console.log(row)
            let payload = {
                type: row['cliente'].ruc.length === 11 ? '01' : '03',
                id: row['movimiento_id'],
            }
            let payloadEncoded = btoa(JSON.stringify(payload));
            return '<a href="/receipt/' + payloadEncoded + '/pdf?preview=true" class="text-reset" target="_blank"><i class="fas fa-file-invoice fa-lg"></i> ' + value + '</a>';
        } else {
            return '';
        }
    }

    static invoiceStatusFormatter(value: number | null) {
        if (value === null) {
            value = -1
        } else {
            value = Number(value);
        }
        if (value === 1) {
            return '<span class="badge rounded-pill bg-success">NO</span>';
        } else if (value === 2) {
            return '<span class="badge rounded-pill bg-danger">SI</span>';
        } else {
            return '';
        }
    }

    static billStatusFormatter(value: number | null) {
        if (value === null) {
            value = -1
        } else {
            value = Number(value);
        }
        if (value === -1) {
            return '<span class="badge rounded-pill bg-primary">PENDIENTE</span>';
        } else if (value === 0) {
            return '<span class="badge rounded-pill bg-success">ENVIADO</span>';
        } else if (value < 2000) {
            return '<span class="badge rounded-pill bg-warning">ERRONEO</span>';
        } else if (value >= 2000 && value < 4000) {
            return '<span class="badge rounded-pill bg-danger">RECHAZADO</span>';
        } else if (value >= 4000) {
            return '<span class="badge rounded-pill bg-info">OBSERVADO</span>';
        }
    }

    static dxBillStatusFormatter(cell: ColumnCellTemplateData) {
        if (cell.data.envio_sunat === null) {
            cell.data.envio_sunat = -1
        } else {
            cell.data.envio_sunat = Number(cell.data.envio_sunat);
        }
        if (cell.data.envio_sunat === -1) {
            return <span className="badge rounded-pill bg-primary">PENDIENTE</span>
        } else if (cell.data.envio_sunat === 0) {
            return <span className="badge rounded-pill bg-success">ENVIADO</span>
        } else if (cell.data.envio_sunat < 2000) {
            return <span className="badge rounded-pill bg-warning">ERRONEO</span>
        } else if (cell.data.envio_sunat >= 2000 && cell.data.envio_sunat < 4000) {
            return <span className="badge rounded-pill bg-danger">RECHAZADO</span>
        } else if (cell.data.envio_sunat >= 4000) {
            return <span className="badge rounded-pill bg-info">OBSERVADO</span>
        }
    }

    static voidedFormatter(value: number) {
        if (value === 1) {
            return '<span class="badge rounded-pill bg-success">NO</span>';
        }
        if (value === 2) {
            return '<span class="badge rounded-pill bg-danger">SI</span>';
        } else {
            return '<span class="badge rounded-pill bg-warning">CON ERROR</span>';
        }
    }

    static dateFormatter(value: string) {
        const date = new Date(value);
        return date.toLocaleDateString('es-PE', {timeZone: 'UTC'});
    }

    static timeFormatter(value: string) {
        const time = new Date(value);
        return time.toLocaleTimeString('es-PE', {timeZone: 'UTC'});
    }

    static dateTimeFormatter(value: string) {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        const dateTime = new Date(value);
        const config: Intl.DateTimeFormatOptions = {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return new Intl.DateTimeFormat('es-PE', config).format(dateTime);
    }
}
