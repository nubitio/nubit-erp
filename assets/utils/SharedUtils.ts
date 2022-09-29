export class SharedUtils {
    static randomId(): string {
        return Math.random().toString(36).substring(2, 15);
    }

    static showMessage(title: String, message: string) {
        ($ as any).messager.show({
            title: title,
            msg: message
        });
    }

    static activeFormatter(val) {
        if (Number(val) === 1) {
            return '<span class="badge rounded-pill bg-success">SI</span>';
        } else {
            return '<span class="badge rounded-pill bg-danger">NO</span>';
        }
    }

    static imageFormatter(image, name, path = '') {
        if (image) {
            return `<a href="${path}${image}" data-lightbox="${name}">
                    <img src="${path}${image}" style="width: 30px; height: 30px;"  alt=""/>
                </a>`;
        } else {
            return `<a href="https://ui-avatars.com/api/?name=${name}" data-lightbox="${name}">
                    <img src="https://ui-avatars.com/api/?name=${name}" style="width: 30px; height: 30px;"  alt=""/>
                </a>`;
        }
    }

    static voucherFormatter(value, row) {
        if (value !== null) {
            return '<a href="/sale/order/' + row.movimiento_id + '" class="text-reset" target="_blank"><i class="fas fa-receipt fa-lg"></i> ' + row.serie + '-' + row.numero + '</a>';
        } else {
            return '';
        }
    }

    static invoiceFormatter(value, row) {
        if (value !== null) {
            return '<a href="/invoice/download/' + row.movimiento_id + '/pdf?mode=preview" class="text-reset" target="_blank"><i class="fas fa-file-invoice fa-lg"></i> ' + row.iserie + '-' + row.inumero + '</a>';
        } else {
            return '';
        }
    }

    static invoiceStatusFormatter(value, row) {
        if (value === null) {
            value = -1
        } else {
            value = Number(value);
        }
        if (value === 1) {
            return '<span class="badge rounded-pill bg-success">INGRESADO</span>';
        } else if (value === 2) {
            return '<span class="badge rounded-pill bg-danger">ANULADO</span>';
        } else {
            return '';
        }
    }
}